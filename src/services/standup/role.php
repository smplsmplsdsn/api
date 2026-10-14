<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/functions/permissions.php';

function updateError(string $message, int $status = 400) {
  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

try {
  $pdo = createDatabaseConnection();

  // ログイン状態の確認
  $user_id = $_SESSION['services']['standup']['user_id'] ?? null;

  if (!$user_id) {
    updateError('Not logged in', 401);
  }

  // ユーザーの有効状態を確認
  $stmt = $pdo->prepare('
    SELECT id
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
    updateError('Invalid user', 401);
  }

  // member権限の確認
  if (!hasActiveMember($pdo, $user_id, 'standup')) {
    updateError('Member role is inactive', 403);
  }

  // リクエストの取得
  $input = json_decode(
    file_get_contents('php://input'),
    true
  );

  if (!is_array($input)) {
    updateError('Invalid request body', 400);
  }

  $role = $input['role'] ?? '';
  $status = $input['status'] ?? '';

  if (!in_array($role, ['comedian', 'venue_manager'], true)) {
    updateError('Invalid role', 400);
  }

  if (!in_array($status, ['active', 'inactive'], true)) {
    updateError('Invalid status', 400);
  }

  $pdo->beginTransaction();

  // ロールの追加・更新
  $stmt = $pdo->prepare('
    INSERT INTO user_service_roles (
      user_id,
      service_key,
      role,
      status
    ) VALUES (?, ?, ?, ?) AS new
    ON DUPLICATE KEY UPDATE
      status = new.status,
      updated_at = CURRENT_TIMESTAMP
  ');

  $stmt->execute([
    $user_id,
    'standup',
    $role,
    $status,
  ]);

  // comedianロールとプロフィールの状態を連動
  if ($role === 'comedian') {

    if ($status === 'active') {

      // 既存プロフィールの確認
      $stmt = $pdo->prepare('
        SELECT id
        FROM standup_comedians
        WHERE user_id = ?
        FOR UPDATE
      ');

      $stmt->execute([
        $user_id,
      ]);

      $comedian = $stmt->fetch();

      if (!$comedian) {

        // プロフィールがなければinactiveで作成
        $stmt = $pdo->prepare('
          INSERT INTO standup_comedians (
            user_id,
            status
          ) VALUES (?, ?)
        ');

        $stmt->execute([
          $user_id,
          'inactive',
        ]);

      } else {

        // 既存プロフィールの完成条件を再判定
        $stmt = $pdo->prepare("
          UPDATE standup_comedians
          SET status = CASE
            WHEN COALESCE(name, '') <> ''
              AND COALESCE(thumbnail, '') <> ''
            THEN 'active'
            ELSE 'inactive'
          END
          WHERE user_id = ?
        ");

        $stmt->execute([
          $user_id,
        ]);
      }

    } else {

      // comedianロール解除時はプロフィールもinactiveにする
      // 名前・サムネイルなどのプロフィール情報は保持する
      $stmt = $pdo->prepare('
        UPDATE standup_comedians
        SET status = ?
        WHERE user_id = ?
      ');

      $stmt->execute([
        'inactive',
        $user_id,
      ]);
    }
  }

  $pdo->commit();

  echo json_encode([
    'success' => true,
    'role' => $role,
    'status' => $status,
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  error_log($e->getMessage());

  updateError('Internal server error', 500);
}