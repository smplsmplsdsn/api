<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/functions/permissions.php';

// エラー返却
function managerError(string $message, int $status = 400): void {
  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

try {

  // POSTのみ許可
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    managerError('Method not allowed', 405);
  }

  $pdo = createDatabaseConnection();

  // ログインユーザーを確認
  $user_id = $_SESSION['services']['standup']['user_id'] ?? null;

  if (!$user_id) {
    managerError('Unauthorized', 401);
  }

  // ユーザーが有効か確認
  $stmt = $pdo->prepare('
    SELECT id
    FROM users
    WHERE id = :id
      AND status = "active"
    LIMIT 1
  ');

  $stmt->execute([':id' => $user_id]);

  if (!$stmt->fetchColumn()) {
    managerError('Unauthorized', 401);
  }

  $user_id = (int)$user_id;

  // 有効な会場管理者ロールを確認
  $is_venue_manager = hasActiveRole(
    $pdo,
    $user_id,
    'standup',
    'venue_manager'
  );

  // JSONリクエストを取得
  $input = json_decode(
    file_get_contents('php://input'),
    true
  );

  if (!is_array($input)) {
    managerError('Invalid request body', 400);
  }

  $action = $input['action'] ?? null;
  $venue_id = $input['venue_id'] ?? null;

  // 操作を確認
  if (!in_array($action, ['add', 'remove'], true)) {
    managerError('Invalid action', 400);
  }

  // 会場IDの形式を確認
  if (!is_int($venue_id) || $venue_id < 1) {
    managerError('Invalid venue id', 400);
  }

  // 管理者の追加には有効な会場管理者ロールが必要
  if ($action === 'add' && !$is_venue_manager) {
    managerError('Forbidden', 403);
  }

  // ---------------------------------
  // 管理者の追加・解除
  // ---------------------------------
  $pdo->beginTransaction();

  // 会場情報をロックして取得
  $stmt = $pdo->prepare('
    SELECT id, status
    FROM standup_venues
    WHERE id = :venue_id
    LIMIT 1
    FOR UPDATE
  ');

  $stmt->execute([':venue_id' => $venue_id]);

  $venue = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$venue || $venue['status'] !== 'active') {
    throw new RuntimeException('Venue not found');
  }

  // 自分自身の管理者登録を確認
  $stmt = $pdo->prepare('
    SELECT id, status
    FROM standup_venue_managers
    WHERE venue_id = :venue_id
      AND user_id = :user_id
    LIMIT 1
    FOR UPDATE
  ');

  $stmt->execute([
    ':venue_id' => $venue_id,
    ':user_id' => $user_id
  ]);

  $manager = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($action === 'add') {

    // すでに有効な管理者なら何もしない
    if ($manager && $manager['status'] === 'active') {
      $pdo->commit();

      echo json_encode([
        'success' => true
      ], JSON_UNESCAPED_UNICODE);

      exit;
    }

    if ($manager) {

      // 過去に解除した管理者を再有効化
      $stmt = $pdo->prepare('
        UPDATE standup_venue_managers
        SET status = "active"
        WHERE id = :id
      ');

      $stmt->execute([
        ':id' => $manager['id']
      ]);
    } else {

      // 新規登録
      $stmt = $pdo->prepare('
        INSERT INTO standup_venue_managers (
          venue_id,
          user_id,
          status
        ) VALUES (
          :venue_id,
          :user_id,
          "active"
        )
      ');

      $stmt->execute([
        ':venue_id' => $venue_id,
        ':user_id' => $user_id
      ]);
    }
  } else {

    // 自分自身の管理者登録だけを解除
    if ($manager && $manager['status'] === 'active') {
      $stmt = $pdo->prepare('
        UPDATE standup_venue_managers
        SET status = "inactive"
        WHERE id = :id
          AND venue_id = :venue_id
          AND user_id = :user_id
          AND status = "active"
      ');

      $stmt->execute([
        ':id' => $manager['id'],
        ':venue_id' => $venue_id,
        ':user_id' => $user_id
      ]);
    }
  }

  $pdo->commit();

  // 成功レスポンス
  echo json_encode([
    'success' => true
  ], JSON_UNESCAPED_UNICODE);

  exit;

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  // 想定されるエラーは個別に返却
  if ($e instanceof RuntimeException) {
    $message = $e->getMessage();

    if ($message === 'Venue not found') {
      managerError($message, 404);
    }

    if ($message === 'Forbidden') {
      managerError($message, 403);
    }
  }

  // 想定外のエラーはログに記録
  error_log((string)$e);

  managerError('Internal server error', 500);
}