<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/functions/permissions.php';

function comedianThumbnailError(string $message, int $status = 400) {
  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

try {

  if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    comedianThumbnailError('Method not allowed', 405);
  }

  $pdo = createDatabaseConnection();

  $user_id = $_SESSION['services']['standup']['user_id'] ?? null;

  if (!$user_id) {
    comedianThumbnailError('Not logged in', 401);
  }

  // ユーザー確認
  $stmt = $pdo->prepare('
    SELECT
      id,
      public_id
    FROM users
    WHERE id = ?
      AND status = ?
  ');

  $stmt->execute([
    $user_id,
    'active',
  ]);

  $user = $stmt->fetch();

  if (!$user) {
    comedianThumbnailError('Invalid user', 401);
  }

  // コメディアン権限確認
  if (!hasActiveRole($pdo, $user_id, 'standup', 'comedian')) {
    comedianThumbnailError('Comedian role is inactive', 403);
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // プロフィール入力
    $name = trim($_POST['name'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $tiktok = trim($_POST['tiktok'] ?? '');
    $youtube = trim($_POST['youtube'] ?? '');
    $x = trim($_POST['x'] ?? '');

    // コメディアン情報取得
    $stmt = $pdo->prepare('
      SELECT
        id,
        thumbnail,
        thumbnail_temp
      FROM standup_comedians
      WHERE user_id = ?
    ');

    $stmt->execute([
      $user_id,
    ]);

    $comedian = $stmt->fetch();

    if (!$comedian) {
      comedianThumbnailError('Comedian profile not found', 404);
    }

    $thumbnail = $comedian['thumbnail'];
    $thumbnail_temp = $comedian['thumbnail_temp'];

    $delete_paths = [];

    // サムネイル仮保存データの確認
    if ($thumbnail_temp !== '') {

      $public_path = '/' . $user['public_id']
        . '/standup/profile/';

      // 自分のプロフィール画像フォルダ内のパスか確認
      if (str_starts_with($thumbnail_temp, $public_path)) {

        $filename = substr(
          $thumbnail_temp,
          strlen($public_path)
        );

        // ファイル名だけであることを確認
        if (
          $filename !== ''
          && basename($filename) === $filename
        ) {

          $upload_dir = __DIR__
            . '/../../upload/'
            . $user['public_id']
            . '/standup/profile';

          $temp_path = $upload_dir . '/' . $filename;

          // 実体ファイルが存在する場合のみ確定
          if (is_file($temp_path)) {

            $thumbnail = $thumbnail_temp;
            $thumbnail_temp = '';

            // 確定後に削除する対象を記録
            $delete_paths = glob($upload_dir . '/*') ?: [];

            $delete_paths = array_filter(
              $delete_paths,
              function ($path) use ($temp_path) {
                return is_file($path)
                  && realpath($path) !== realpath($temp_path);
              }
            );
          }
        }
      }
    }

    // サムネイルの状態からプロフィール状態を決定
    $status = $name !== '' && $thumbnail !== ''
      ? 'active'
      : 'inactive';

    // プロフィールとサムネイルを更新
    $stmt = $pdo->prepare('
      UPDATE standup_comedians
      SET
        name = ?,
        thumbnail = ?,
        thumbnail_temp = ?,
        instagram = ?,
        tiktok = ?,
        youtube = ?,
        x = ?,
        status = ?,
        updated_at = NOW()
      WHERE
        id = ?
        AND user_id = ?
    ');

    $stmt->execute([
      $name,
      $thumbnail,
      $thumbnail_temp,
      $instagram,
      $tiktok,
      $youtube,
      $x,
      $status,
      $comedian['id'],
      $user_id,
    ]);

    // DB更新後、確定したサムネイル以外を削除
    foreach ($delete_paths as $delete_path) {

      if (is_file($delete_path)) {
        @unlink($delete_path);
      }
    }

    echo json_encode([
      'success' => true,
      'comedian' => [
        'id' => (string) $comedian['id'],
        'name' => $name,
        'thumbnail' => $thumbnail,
        'socialmedia' => [
          'instagram' => $instagram,
          'tiktok' => $tiktok,
          'youtube' => $youtube,
          'x' => $x,
        ],
        'status' => $status,
      ],
    ], JSON_UNESCAPED_UNICODE);

    exit;
  }

  // GET: コメディアン情報取得
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
  ');

  $stmt->execute([
    $user_id,
  ]);

  $comedian = $stmt->fetch();

  if (!$comedian) {
    comedianThumbnailError('Comedian profile not found(2)', 404);
  }

  // レスポンスデータ
  $response = [
    'success' => true,
    'comedian' => [
      'id' => (string) $comedian['id'],
      'name' => $comedian['name'],
      'thumbnail' => $comedian['thumbnail'],
      'socialmedia' => [
        'instagram' => $comedian['instagram'],
        'tiktok' => $comedian['tiktok'],
        'youtube' => $comedian['youtube'],
        'x' => $comedian['x'],
      ],
      'status' => $comedian['status'],
    ],
  ];

  // GET: 参加表明している候補情報取得
  if (($_GET['is_candidate_info'] ?? '') === 'true') {

    $stmt = $pdo->prepare('
      SELECT
        secc.candidate_id
      FROM standup_event_candidate_comedians AS secc
      INNER JOIN standup_comedians AS sc
        ON sc.id = secc.comedian_id
      WHERE sc.user_id = ?
      ORDER BY secc.candidate_id ASC
    ');

    $stmt->execute([
      $user_id,
    ]);

    $response['registered_candidates'] = array_map(
      'strval',
      $stmt->fetchAll(PDO::FETCH_COLUMN)
    );
  }

  echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log($e);
  comedianThumbnailError('Internal server error', 500);
}