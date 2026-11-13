<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/functions/permissions.php';

// エラー返却
function deleteError(string $message, int $status = 400): void {
  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ]);
  exit;
}

try {

  // POSTのみ許可
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    deleteError('Method not allowed', 405);
  }

  // ログインユーザーを確認
  $user_id = $_SESSION['services']['standup']['user_id'] ?? null;

  if (!$user_id) {
    deleteError('Unauthorized', 401);
  }

  $pdo = createDatabaseConnection();

  // ユーザーが有効か確認
  $stmt = $pdo->prepare('
    SELECT public_id
    FROM users
    WHERE id = :id
      AND status = "active"
    LIMIT 1
  ');

  $stmt->execute([':id' => $user_id]);

  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    deleteError('Unauthorized', 401);
  }

  $public_id = $user['public_id'];

  $input = json_decode(file_get_contents('php://input'), true);

  if (!is_array($input)) {
    deleteError('Invalid JSON', 400);
  }

  $id = $input['id'] ?? null;

  // Validate event ID
  if (
    (!is_string($id) && !is_int($id)) ||
    !preg_match('/^[1-9][0-9]*$/', (string)$id)
  ) {
    deleteError('Invalid event ID', 400);
  }

  // Start transaction
  $pdo->beginTransaction();

  // Get event
  $stmt = $pdo->prepare('
    SELECT creator_public_id
    FROM standup_events
    WHERE id = :id
    LIMIT 1
    FOR UPDATE
  ');

  $stmt->execute([
    ':id' => $id
  ]);

  $event = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$event) {
    throw new RuntimeException('Event not found');
  }

  // Check creator permission
  if ($event['creator_public_id'] !== $public_id) {
    throw new RuntimeException('Permission denied');
  }

  // Delete event
  $stmt = $pdo->prepare('
    DELETE FROM standup_events
    WHERE id = :id
  ');

  $stmt->execute([
    ':id' => $id
  ]);

  // Commit
  $pdo->commit();

  echo json_encode([
    'success' => true
  ]);

} catch (Exception $e) {

  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  // 想定されるエラーは個別に返却
  if ($e instanceof RuntimeException) {
    $message = $e->getMessage();

    if ($message === 'Event not found') {
      deleteError($message, 404);
    }

    if ($message === 'Permission denied') {
      deleteError($message, 403);
    }
  }

  // 想定外のエラーはログに記録
  error_log((string)$e);

  deleteError('通信エラーが発生しました', 500);
}