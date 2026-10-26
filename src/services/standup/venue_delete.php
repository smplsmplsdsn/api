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
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

try {

  // POSTのみ許可
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    deleteError('Method not allowed', 405);
  }

  $pdo = createDatabaseConnection();

  // ログインユーザーを確認
  $user_id = $_SESSION['services']['standup']['user_id'] ?? null;

  if (!$user_id) {
    deleteError('Unauthorized', 401);
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
    deleteError('Unauthorized', 401);
  }

  $user_id = (int)$user_id;

  // 有効なコメディアンロールを確認
  $is_comedian_role = hasActiveRole(
    $pdo,
    $user_id,
    'standup',
    'comedian'
  );

  // 有効なコメディアン情報があるか確認
  $is_active_comedian = $is_comedian_role
    && getActiveComedianId($pdo, $user_id) !== null;

  // 有効な会場管理者ロールを確認
  $is_venue_manager = hasActiveRole(
    $pdo,
    $user_id,
    'standup',
    'venue_manager'
  );

  // コメディアンまたは会場管理者であること
  if (!$is_active_comedian && !$is_venue_manager) {
    deleteError('Forbidden', 403);
  }

  // 会場IDの形式を確認
  $input = json_decode(
    file_get_contents('php://input'),
    true
  );

  $id = $input['id'] ?? null;

  if (!is_int($id) || $id < 1) {
    deleteError('Invalid venue id', 400);
  }

  $venue_id = (int)$id;

  // ---------------------------------
  // 削除・非表示処理
  // ---------------------------------
  $pdo->beginTransaction();

  // 会場情報をロックして取得
  $stmt = $pdo->prepare('
    SELECT id, status
    FROM standup_venues
    WHERE id = :id
    LIMIT 1
    FOR UPDATE
  ');

  $stmt->execute([':id' => $venue_id]);

  $venue = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$venue || $venue['status'] !== 'active') {
    throw new RuntimeException('Venue not found');
  }

  // 有効な担当管理者がいるか確認
  $stmt = $pdo->prepare('
    SELECT 1
    FROM standup_venue_managers
    WHERE venue_id = :venue_id
      AND status = "active"
    LIMIT 1
  ');

  $stmt->execute([':venue_id' => $venue_id]);

  $has_managers = (bool)$stmt->fetchColumn();

  // 有効な担当管理者がいる場合は担当管理者のみ削除可能
  if ($has_managers) {
    if (!$is_venue_manager) {
      throw new RuntimeException('Forbidden');
    }

    $stmt = $pdo->prepare('
      SELECT 1
      FROM standup_venue_managers
      WHERE venue_id = :venue_id
        AND user_id = :user_id
        AND status = "active"
      LIMIT 1
    ');

    $stmt->execute([
      ':venue_id' => $venue_id,
      ':user_id' => $user_id
    ]);

    if (!$stmt->fetchColumn()) {
      throw new RuntimeException('Forbidden');
    }
  }

  // 未来または日時未定のイベントで使用中か確認
  $stmt = $pdo->prepare('
    SELECT 1
    FROM standup_event_candidates
    WHERE venue_id = :venue_id
      AND (
        COALESCE(start_at, open_at) >= NOW()
        OR (start_at IS NULL AND open_at IS NULL)
      )
    LIMIT 1
  ');

  $stmt->execute([':venue_id' => $venue_id]);

  if ($stmt->fetchColumn()) {
    throw new RuntimeException(
      'Venue is used by a future or undated event'
    );
  }

  // 過去イベントを含め、イベントで使用された履歴があるか確認
  $stmt = $pdo->prepare('
    SELECT 1
    FROM standup_event_candidates
    WHERE venue_id = :venue_id
    LIMIT 1
  ');

  $stmt->execute([':venue_id' => $venue_id]);

  $has_event_history = (bool)$stmt->fetchColumn();

  if ($has_event_history) {
    // 履歴がある場合は物理削除せず非表示にする
    $stmt = $pdo->prepare('
      UPDATE standup_venues
      SET status = "inactive"
      WHERE id = :venue_id
        AND status = "active"
    ');

    $stmt->execute([':venue_id' => $venue_id]);

    if ($stmt->rowCount() !== 1) {
      throw new RuntimeException('Failed to deactivate venue');
    }

    $action = 'deactivated';
  } else {
    // イベントで未使用の場合は物理削除
    $stmt = $pdo->prepare('
      DELETE FROM standup_venues
      WHERE id = :venue_id
        AND status = "active"
    ');

    $stmt->execute([':venue_id' => $venue_id]);

    if ($stmt->rowCount() !== 1) {
      throw new RuntimeException('Failed to delete venue');
    }

    $action = 'deleted';
  }

  $pdo->commit();

  // 成功レスポンス
  echo json_encode([
    'success' => true,
    'id' => $venue_id,
    'action' => $action
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
      deleteError($message, 404);
    }

    if ($message === 'Forbidden') {
      deleteError($message, 403);
    }

    if ($message === 'Venue is used by a future or undated event') {
      deleteError($message, 400);
    }
  }

  // 想定外のエラーはログに記録
  error_log((string)$e);

  deleteError('Internal server error', 500);
}
