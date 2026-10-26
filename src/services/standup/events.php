<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../app/functions/permissions.php';

// エラー返却
function updateError(string $message, int $status = 400): void {
  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ], JSON_UNESCAPED_UNICODE);

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
$stmt = $pdo->query('
  SELECT
    id,
    creator_public_id,
    type,
    name,
    message,
    message_for_comedian,
    image,
    status,
    confirmed_candidate_id
  FROM standup_events
  ORDER BY id DESC
');

$event_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// イベント情報（候補日の有無に関係なく全件取得）
$events = [];

foreach ($event_rows as $row) {
  $event_id = (int) $row['id'];

  $events[$event_id] = [
    'id' => (string)$event_id,
    'creator_public_id' => $row['creator_public_id'],
    'type' => $row['type'],
    'name' => $row['name'],
    'message' => $row['message'],
    'message_for_comedian' => (
      $is_comedian ||
      $row['creator_public_id'] === $public_id
    )
      ? $row['message_for_comedian']
      : '',
    'image' => $row['image'],
    'status' => $row['status'],
    'confirmed_candidate_id' => $row['confirmed_candidate_id']
      !== null
      ? (int) $row['confirmed_candidate_id']
      : null,
  ];
}

// 候補日情報
$stmt = $pdo->query('
  SELECT
    id,
    event_id,
    venue_id,
    open_at,
    start_at,
    fee
  FROM standup_event_candidates
  ORDER BY
    COALESCE(start_at, open_at) ASC,
    id ASC
');

$candidate_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 現在日時
$now = new DateTime();

// 候補日
$event_candidates = [];

foreach ($candidate_rows as $row) {
  $event_id = (string) $row['event_id'];
  $candidate_id = (int) $row['id'];

  // イベント情報が存在しない場合
  if (!isset($events[$event_id])) {
    continue;
  }

  // 日時判定
  $candidate_datetime = null;

  if ($row['start_at'] !== null) {
    $candidate_datetime = new DateTime($row['start_at']);
  } elseif ($row['open_at'] !== null) {
    $candidate_datetime = new DateTime($row['open_at']);
  }

  // 日時未定の場合
  if ($candidate_datetime === null) {

    // ガード（作成者本人ではない、もしくは comedian でも venue_manager でもない場合）
    if (
      $events[$event_id]['creator_public_id'] !== $public_id ||
      (!$is_comedian && !$is_venue_manager)
    ) {
      continue;
    }

  // 過去の場合は取得対象外
  } elseif ($candidate_datetime <= $now) {
    continue;
  }

  // 候補日を追加
  $event_candidates[$candidate_id] = [
    'id' => (string)$candidate_id,
    'event_id' => $event_id,
    'venue_id' => $row['venue_id'] !== null
      ? (string) $row['venue_id']
      : null,
    'open_at' => $row['open_at'],
    'start_at' => $row['start_at'],
    'fee' => $row['fee'],
    'comedian_ids' => [],
  ];
}

// 候補日に出演するコメディアンを取得
$candidate_id_list = array_keys($event_candidates);

if (count($candidate_id_list) > 0) {
  $placeholders = implode(
    ',',
    array_fill(0, count($candidate_id_list), '?')
  );

  $stmt = $pdo->prepare("
    SELECT
      candidate_id,
      comedian_id
    FROM standup_event_candidate_comedians
    WHERE candidate_id IN ($placeholders)
    ORDER BY
      candidate_id ASC,
      id ASC
  ");

  $stmt->execute($candidate_id_list);

  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $candidate_id = (int) $row['candidate_id'];
    $comedian_id = (string) $row['comedian_id'];

    if (isset($event_candidates[$candidate_id])) {
      $event_candidates[$candidate_id]['comedian_ids'][] = $comedian_id;
    }
  }
}

// 通常の配列へ変換
$events = array_values($events);
$event_candidates = array_values($event_candidates);


echo json_encode([
  'events' => $events,
  'event_candidates' => $event_candidates,
], JSON_UNESCAPED_UNICODE);