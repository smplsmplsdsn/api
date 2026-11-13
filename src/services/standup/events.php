<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../app/functions/permissions.php';
require_once __DIR__ . '/../../../app/functions/event.php';

// エラー返却
function updateError(string $message, int $status = 400): void {
  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

// GETのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  header('Allow: GET');
  exit;
}

$pdo = createDatabaseConnection();

// ログインユーザーを確認
$user_id = $_SESSION['services']['standup']['user_id'] ?? null;

if (!$user_id) {
  updateError('Unauthorized', 401);
}

// メンバーか確認
if (!hasActiveMember($pdo, $user_id, 'standup')) {
  updateError('Unauthorized', 401);
}

// ユーザーが有効か確認
$stmt = $pdo->prepare('
  SELECT
    id,
    public_id
  FROM users
  WHERE id = :id
    AND status = "active"
  LIMIT 1
');

$stmt->execute([':id' => $user_id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  updateError('Unauthorized', 401);
}

$user_id = (int)$user['id'];
$public_id = $user['public_id'];

// 有効なコメディアン情報を取得
$comedian_id = getActiveComedianId($pdo, $user_id);

$is_comedian = $comedian_id !== null;

// 有効な会場管理者の確認
$is_venue_manager = hasActiveRole(
  $pdo,
  $user_id,
  'standup',
  'venue_manager'
);


/*
 * イベント取得
 */
$event_data = getStandupEvents(
  $pdo,
  $public_id,
  $is_comedian,
  $is_venue_manager
);

$events = $event_data['events'];
$event_candidates = $event_data['event_candidates'];


echo json_encode([
  'events' => $events,
  'event_candidates' => $event_candidates,
], JSON_UNESCAPED_UNICODE);