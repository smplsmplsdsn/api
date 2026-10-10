<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/functions/ulid.php';
require_once __DIR__ . '/../../../app/functions/image.php';

function comedianThumbnailError(string $message, int $status = 400) {
  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

try {

  // POST確認
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    comedianThumbnailError(
      'POSTでアクセスしてください',
      405
    );
  }

  $pdo = createDatabaseConnection();

  $user_id = $_SESSION['services']['standup']['user_id'] ?? null;

  if (!$user_id) {
    comedianThumbnailError(
      'ログインしてください',
      401
    );
  }

  // ユーザー確認
  $stmt = $pdo->prepare('
    SELECT
      id,
      public_id,
      status
    FROM users
    WHERE id = ?
    LIMIT 1
  ');

  $stmt->execute([
    $user_id
  ]);

  $user = $stmt->fetch();

  if (!$user) {
    comedianThumbnailError(
      'ユーザーが見つかりません',
      401
    );
  }

  if ($user['status'] !== 'active') {
    comedianThumbnailError(
      'ユーザーが無効です',
      403
    );
  }

  $stmt = $pdo->prepare('
    SELECT
      role,
      status
    FROM user_service_roles
    WHERE
      user_id = ?
      AND service_key = ?
      AND role = ?
    LIMIT 1
  ');

  $stmt->execute([
    $user_id,
    'standup',
    'comedian'
  ]);

  $role = $stmt->fetch();

  if (!$role || $role['status'] !== 'active') {
    comedianThumbnailError(
      'コメディアン権限がありません',
      403
    );
  }

  // コメディアン確認
  $stmt = $pdo->prepare('
    SELECT
      id,
      name,
      thumbnail,
      instagram,
      tiktok,
      youtube,
      x,
      status
    FROM standup_comedians
    WHERE user_id = ?
    LIMIT 1
  ');

  $stmt->execute([
    $user_id
  ]);

  $comedian = $stmt->fetch();

  if (!$comedian) {
    comedianThumbnailError(
      'コメディアン情報が見つかりません',
      404
    );
  }

  // アップロードファイル確認
  if (!isset($_FILES['thumbnail']) || !is_array($_FILES['thumbnail'])) {
    comedianThumbnailError(
      '画像がありません',
      400
    );
  }

  $file = $_FILES['thumbnail'];
  $filename = generateUlid();

  // 保存先
  $public_path = $user['public_id'] . '/standup/profile';
  $upload_path = __DIR__ . '/../../upload/' .  $public_path;

  if (!is_dir($upload_path) && !mkdir($upload_path, 0755, true) && !is_dir($upload_path)) {
    imageUploadError('IMAGE_DIRECTORY_CREATE_FAILED');
  }

  $target_path = $upload_path . '/' . $filename;

  // アップロード先フォルダ $upload_path のファイル一覧（アップロード後の削除対象）
  $old_files = glob($upload_path . '/*') ?: [];

  // 画像保存
  try {
    $saved = saveImage($file, $target_path, 400, 0.5 * 1024 * 1024);
  } catch (Throwable $e) {

    if ($e->getMessage()) {
      comedianThumbnailError(
        '画像を保存できませんでした' . $e->getMessage(),
        400
      );
    } else {
      comedianThumbnailError(
        '画像を保存できませんでした',
        500
      );
    }
  }

  // 公開用パス: saveImage()が決定した拡張子を使用する
  $thumbnail_path = '/' . $public_path . '/' . basename($saved['upload_path']);

  $status = ($comedian['name'] !== '') ? 'active' : 'inactive';

  try {
    $stmt = $pdo->prepare('
      UPDATE standup_comedians
      SET
        thumbnail = ?,
        status = ?,
        updated_at = NOW()
      WHERE
        id = ?
        AND user_id = ?
    ');

    $stmt->execute([
      $thumbnail_path,
      $status,
      $comedian['id'],
      $user_id
    ]);

  } catch (Throwable $e) {

    // DB更新に失敗した場合、今回保存したファイルを削除
    @unlink($saved['upload_path']);

    comedianThumbnailError(
      'プロフィールを更新できませんでした',
      500
    );
  }

  // 既存ファイルを削除する
  foreach ($old_files as $old_file_path) {
    if (is_file($old_file_path)) {
      @unlink($old_file_path);
    }
  }

  echo json_encode([
    'success' => true,
    'comedian' => [
      'thumbnail' => $thumbnail_path,
      'status' => $status
    ]
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  error_log($e);

  comedianThumbnailError(
    'Internal server error',
    500
  );
}