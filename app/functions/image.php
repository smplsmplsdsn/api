<?php
/*
 * NOTE:
 * メモリ・CPU負荷対策
 * 最大辺 12,000px以下、7,000万画素以下の画像のみ対象
 *
 * processImage で画像アップロードエラーが多発したときの簡易ログ確認方法
 * （Dockerの場合）
 * ファイル確認: docker compose exec php ls -l /tmp/process_image_error.log
 * 中身確認: docker compose exec php cat /tmp/process_image_error.log
 * 直近20件確認: docker compose exec php tail -n 20 /tmp/process_image_error.log
 * （Docker未使用の場合）
 * OSの /tmp に書き出される
 */

/**
 * 画像処理の振り分け
 * 画像を検証し、ImagickまたはGDに処理を振り分ける
 */
function processImage(array $file, int $max_side = 0, int $max_size = 0) {

  // ガード（ファイルのアップロードが正常に完了していない場合）
  if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
    error_log('Upload error code: ' . ($file['error'] ?? 'missing'));
    throw new RuntimeException('UPLOAD_FAILED');
  }

  // ガード（HTTPアップロードで送られてきたファイルではない場合）
  if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    throw new RuntimeException('INVALID_UPLOAD');
  }

  $allowed = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/heic',
    'image/heif'
  ];


  $finfo = finfo_open(FILEINFO_MIME_TYPE);

  // ガード（MIME判定機能が使えない場合）
  if (!$finfo) {
    throw new RuntimeException('MIME_DETECTION_FAILED');
  }

  $mime = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);

  if (!in_array($mime, $allowed, true)) {
    throw new RuntimeException('UNSUPPORTED_IMAGE_TYPE');
  }

  // Imagick優先で処理する
  if (extension_loaded('imagick')) {

    try {
      $result = processImageWithImagick($file['tmp_name'], $mime, $max_side, $max_size);
    } catch (Throwable $e) {

      // 画像処理エラーの調査用ログ
      file_put_contents(
        '/tmp/process_image_error.log',
        $e->getMessage() . PHP_EOL,
        FILE_APPEND
      );

      // Imagickの例外発生時のフォールバック
      $result = processImageWithGd($file['tmp_name'], $mime, $max_side, $max_size);
    }
  } else {

    // Imagickが使えない場合のフォールバック
    $result = processImageWithGd($file['tmp_name'], $mime, $max_side, $max_size);
  }

  // 処理結果がない場合
  if ($result === null) {
    throw new RuntimeException('IMAGE_PROCESS_FAILED');
  }

  // 加工後の画像サイズを確認
  if ($max_size > 0 && strlen($result['blob']) > $max_size) {
    throw new RuntimeException('IMAGE_TOO_LARGE');
  }

  return $result;
}

/**
 * Imagick画像処理
 * Imagickで画像のリサイズや圧縮などを行う
 */
function processImageWithImagick(
  string $file_path,
  string $mime,
  int $max_side,
  int $max_size
) {
  $img = new Imagick();

  try {
    $img->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256);
    $img->setResourceLimit(Imagick::RESOURCETYPE_MAP, 256);
    $img->readImage($file_path);

    $width = $img->getImageWidth();
    $height = $img->getImageHeight();

    // ガード（最大辺または総ピクセル数を超えている場合）
    if ($width > 12000 || $height > 12000 || ($width * $height) > 70000000) {
      return null;
    }

    // EXIFの向きを画像そのものに反映
    $img->autoOrient();

    // ICCプロファイルを取得
    $icc = $img->getImageProfile('icc');

    // メタデータを削除
    $img->stripImage();

    // ICCプロファイルを復元
    if ($icc) {
      $img->setImageProfile('icc', $icc);
    }

    // HEIC / HEIFはJPEGへ変換
    if ($mime === 'image/heic' || $mime === 'image/heif') {
      $img->setImageBackgroundColor(new ImagickPixel('white'));

      $flattened = $img->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

      $img->clear();
      $img->destroy();

      $img = $flattened;
      $img->setImageFormat('jpeg');
      $img->setImageCompression(Imagick::COMPRESSION_JPEG);
      $img->setImageCompressionQuality(100);

      $mime = 'image/jpeg';
    }

    // 最大辺
    if ($max_side > 0) {
      $width = $img->getImageWidth();
      $height = $img->getImageHeight();
      $scale = min($max_side / $width, $max_side / $height, 1);

      if ($scale < 1) {
        $new_width = max(1, (int) ($width * $scale));
        $new_height = max(1, (int) ($height * $scale));

        $img->resizeImage($new_width, $new_height, Imagick::FILTER_LANCZOS, 1);
      }
    }

    // JPEG・WebPのみサイズ圧縮
    if ($max_size > 0 && ($mime === 'image/jpeg' || $mime === 'image/webp')) {
      $blob = createCompressedImageBlobWithImagick($img, $max_size);
    } else {
      $blob = $img->getImagesBlob();
    }

    return [
      'blob' => $blob,
      'mime' => $mime
    ];

  } finally {
    $img->clear();
    $img->destroy();
  }
}

/**
 * GD画像処理（Imagickのフォールバック）
 * GDで画像のリサイズや圧縮などを行う
 */
function processImageWithGd(
  string $file_path,
  string $mime,
  int $max_side,
  int $max_size
) {
  // ガード（HEIC / HEIFはGDで処理できないため）
  if ($mime === 'image/heic' || $mime === 'image/heif') {
    return null;
  }

  // ガード（GDの画像処理機能が使えない場合）
  if (!function_exists('imagecreatefromstring')) {
    return null;
  }

  $blob = file_get_contents($file_path);

  // ガード（ファイルを読み込めない場合）
  if ($blob === false) {
    return null;
  }

  $src = imagecreatefromstring($blob);

  // ガード（画像として読み込めない場合）
  if ($src === false) {
    return null;
  }

  try {

    // EXIFの回転・反転情報を補正
    $oriented_src = orientImageWithGd($src, $file_path, $mime);

    // ガード（向きの補正に失敗した場合）
    if ($oriented_src === false) {
      return null;
    }

    if ($oriented_src !== $src) {
      imagedestroy($src);
      $src = $oriented_src;
    }


    $width = imagesx($src);
    $height = imagesy($src);

    // ガード（最大辺または総ピクセル数を超えている場合）
    if ($width > 12000 || $height > 12000 || ($width * $height) > 70000000) {
      return null;
    }

    $scale = 1;

    if ($max_side > 0) {
      $scale = min($max_side / $width, $max_side / $height, 1);
    }

    $new_width = max(1, (int) round($width * $scale));
    $new_height = max(1, (int) round($height * $scale));

    if ($new_width !== $width || $new_height !== $height) {
      $dst = imagecreatetruecolor($new_width, $new_height);

      if ($dst === false) {
        return null;
      }

      // PNG・透過WebPのアルファチャンネルを維持
      if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
      }

      if (!imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height)) {
        imagedestroy($dst);
        return null;
      }

      imagedestroy($src);
      $src = $dst;
    }

    // JPEG・WebPのみサイズ圧縮
    if ($max_size > 0 && ($mime === 'image/jpeg' || $mime === 'image/webp')) {
      $result = createCompressedImageBlobWithGd($src, $mime, $max_size);
    } else {
      ob_start();

      switch ($mime) {
        case 'image/jpeg':
          imagejpeg($src, null, 100);
          break;
        case 'image/png':
          imagepng($src);
          break;
        case 'image/webp':
          imagewebp($src, null, 100);
          break;
        default:
          ob_end_clean();
          return null;
      }

      $result = ob_get_clean();
    }

    // 出力データが取得できなかった場合
    if ($result === false || $result === null) {
      return null;
    }

    return [
      'blob' => $result,
      'mime' => $mime
    ];

  } finally {
    imagedestroy($src);
  }
}

/**
 * Imagick画像圧縮
 * Imagickで画像を圧縮し、画像データを生成する
 */
function createCompressedImageBlobWithImagick(Imagick $img, int $max_size) {
  $low = 10;
  $high = 100;
  $best = null;

  while ($low <= $high) {
    $quality = (int) floor(($low + $high) / 2);

    $img->setImageCompressionQuality($quality);
    $blob = $img->getImagesBlob();

    if (strlen($blob) <= $max_size) {
      $best = $blob;
      $low = $quality + 1;
    } else {
      $high = $quality - 1;
    }
  }

  if ($best !== null) {
    return $best;
  }

  // 品質10でも指定サイズを超える可能性がある
  $img->setImageCompressionQuality(10);

  return $img->getImagesBlob();
}

/**
 * GD画像圧縮
 * GDで画像を圧縮し、画像データを生成する
 */
function createCompressedImageBlobWithGd(GdImage $img, string $mime, int $max_size) {
  $low = 10;
  $high = 100;
  $best = null;

  while ($low <= $high) {
    $quality = (int) floor(($low + $high) / 2);

    ob_start();

    switch ($mime) {
      case 'image/jpeg':
        imagejpeg($img, null, $quality);
        break;
      case 'image/webp':
        imagewebp($img, null, $quality);
        break;
      default:
        ob_end_clean();
        return null;
    }

    $blob = ob_get_clean();

    if ($blob === false) {
      return null;
    }

    if (strlen($blob) <= $max_size) {
      $best = $blob;
      $low = $quality + 1;
    } else {
      $high = $quality - 1;
    }
  }

  if ($best !== null) {
    return $best;
  }

  // 品質10でも指定サイズを超える可能性がある
  ob_start();

  if ($mime === 'image/jpeg') {
    imagejpeg($img, null, 10);
  } else {
    imagewebp($img, null, 10);
  }

  return ob_get_clean();
}

/**
 * GD画像のEXIF Orientationを補正する
 */
function orientImageWithGd($src, string $file_path, string $mime) {

  // JPEG以外は対象外
  if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
    return $src;
  }

  $exif = @exif_read_data($file_path, 'IFD0');

  $orientation = (int) ($exif['Orientation'] ?? 1);

  switch ($orientation) {
    case 2:
      return imageflip($src, IMG_FLIP_HORIZONTAL) ? $src : false;

    case 3:
      $rotated_src = imagerotate($src, 180, 0);
      return $rotated_src === false ? false : $rotated_src;

    case 4:
      return imageflip($src, IMG_FLIP_VERTICAL) ? $src : false;

    case 5:
      $rotated_src = imagerotate($src, -90, 0);

      if ($rotated_src === false) {
        return false;
      }

      if (!imageflip($rotated_src, IMG_FLIP_HORIZONTAL)) {
        imagedestroy($rotated_src);
        return false;
      }

      return $rotated_src;

    case 6:
      $rotated_src = imagerotate($src, -90, 0);
      return $rotated_src === false ? false : $rotated_src;

    case 7:
      $rotated_src = imagerotate($src, 90, 0);

      if ($rotated_src === false) {
        return false;
      }

      if (!imageflip($rotated_src, IMG_FLIP_HORIZONTAL)) {
        imagedestroy($rotated_src);
        return false;
      }

      return $rotated_src;

    case 8:
      $rotated_src = imagerotate($src, 90, 0);
      return $rotated_src === false ? false : $rotated_src;

    default:
      return $src;
  }
}

/**
 * 撮影日時の取得
 * EXIFから撮影日時とUTCオフセットを取得する
 */
function getImageShotAt(string $file_path): ?array {
  if (!is_file($file_path)) {
    return null;
  }

  $exif = @exif_read_data($file_path, null, true);

  if (!is_array($exif)) {
    return null;
  }

  $shot_at = $exif['EXIF']['DateTimeOriginal']
    ?? $exif['EXIF']['DateTimeDigitized']
    ?? $exif['IFD0']['DateTime']
    ?? null;

  $shot_at_offset = $exif['EXIF']['OffsetTimeOriginal']
    ?? $exif['EXIF']['OffsetTimeDigitized']
    ?? $exif['EXIF']['OffsetTime']
    ?? null;

  if (!$shot_at) {
    return null;
  }

  $date = DateTime::createFromFormat(
    '!Y:m:d H:i:s',
    $shot_at
  );

  if (!$date || $date->format('Y:m:d H:i:s') !== $shot_at) {
    return null;
  }

  return [
    'shot_at' => $date->format('Y-m-d H:i:s'),
    'shot_at_offset' => $shot_at_offset ?: null
  ];
}

/**
 * 画像ファイル保存
 * 画像を検証・加工し、拡張子を統一して保存する
 */
function saveImage(array $file, string $target_path, int $max_side = 0, int $max_size = 0, bool $is_exif_time = false) {

  // 画像を検証・加工する
  $result = processImage($file, $max_side, $max_size);

  $blob = $result['blob'];

  // 加工後のMIMEタイプから拡張子を決める
  $extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
  ];

  if (!isset($extensions[$result['mime']])) {
    throw new RuntimeException('IMAGE_FORMAT_NOT_SUPPORTED');
  }

  $extension = $extensions[$result['mime']];

  // 元の拡張子を変更後の拡張子に置き換える
  $directory = dirname($target_path);
  $filename = pathinfo($target_path, PATHINFO_FILENAME);

  $upload_path = $directory . DIRECTORY_SEPARATOR . $filename . '.' . $extension;

  // ディレクトリがなければ作成する
  if (!is_dir($directory)) {
    if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
      throw new RuntimeException('IMAGE_DIRECTORY_CREATE_FAILED');
    }
  }

  // 書き込み可能か確認する
  if (!is_writable($directory)) {
    throw new RuntimeException('IMAGE_DIRECTORY_NOT_WRITABLE');
  }

  // 一時ファイルを作成する
  $temp_path = tempnam($directory, 'upload_');

  // 一時ファイルが保存先ディレクトリ内に作成されたか確認する
  if ($temp_path === false || realpath(dirname($temp_path)) !== realpath($directory)) {
    if ($temp_path !== false) {
      @unlink($temp_path);
    }

    throw new RuntimeException('IMAGE_SAVE_FAILED');
  }

  // 一時ファイルに加工済み画像を書き込む
  $written = file_put_contents($temp_path, $blob, LOCK_EX);

  if ($written === false || $written !== strlen($blob)) {
    @unlink($temp_path);

    throw new RuntimeException('IMAGE_SAVE_FAILED');
  }

  // 一時ファイルを正式な保存先に移動する
  if (!rename($temp_path, $upload_path)) {
    @unlink($temp_path);

    throw new RuntimeException('IMAGE_RENAME_FAILED');
  }


  // 返却データを作成する
  $response = [
    'upload_path' => $upload_path
  ];

  if ($is_exif_time) {
    $response['exif_time'] = getImageShotAt($file['tmp_name']);
  }

  return $response;
}