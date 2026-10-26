<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../app/functions/permissions.php';

$pdo = createDatabaseConnection();

// ログインユーザー
$user_id = $_SESSION['services']['standup']['user_id'] ?? null;

$is_banned = false;

// member権限の確認
if ($user_id !== null && !hasActiveMember($pdo, $user_id, 'standup')) {
  $user_id = null;
  $is_banned = true;
}

$logged_in = false;
$user_public_id = '';
$nickname = '';
$profiles = [];

$public_id = null;
$is_comedian = false;
$is_venue_manager = false;

if ($user_id !== null) {
  $stmt = $pdo->prepare('
    SELECT
      u.id,
      u.public_id,
      u.nickname
    FROM users AS u
    WHERE u.id = ?
      AND u.status = ?
  ');

  $stmt->execute([
    $user_id,
    'active',
  ]);

  $user = $stmt->fetch();

  if ($user) {
    $logged_in = true;
    $user_public_id = $user['public_id'];
    $nickname = $user['nickname'];

    $public_id = $user['public_id'];

    // Standupのプロフィール（ロール）取得
    $stmt = $pdo->prepare('
      SELECT
        role
      FROM user_service_roles
      WHERE user_id = ?
        AND service_key = ?
        AND status = ?
    ');

    $stmt->execute([
      $user['id'],
      'standup',
      'active',
    ]);

    foreach ($stmt->fetchAll() as $role) {
      $profiles[] = $role['role'];
    }

    $stmt = $pdo->prepare('
      SELECT EXISTS (
        SELECT 1
        FROM standup_comedians
        WHERE user_id = ?
          AND status = ?
      )
    ');

    $stmt->execute([
      $user['id'],
      'active',
    ]);

    $comedian_record_active = (bool) $stmt->fetchColumn();

    $is_comedian = in_array('comedian', $profiles, true) && $comedian_record_active;
    $is_venue_manager = in_array('venue_manager', $profiles, true);

  }
}

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
    'id' => $event_id,
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


/*
 * 会場取得
 */
$venues = [];

$stmt = $pdo->query("
  SELECT
    v.id,
    v.name,
    v.address,
    v.latitude,
    v.longitude,
    v.stations,
    v.instagram,
    v.tiktok,
    v.youtube,
    v.x,
    v.message,
    v.message_for_comedian,
    v.status,
    (
      SELECT JSON_ARRAYAGG(u.public_id)
      FROM standup_venue_managers vm
      INNER JOIN user_service_roles r
        ON r.user_id = vm.user_id
        AND r.service_key = 'standup'
        AND r.role = 'venue_manager'
        AND r.status = 'active'
      INNER JOIN users u
        ON u.id = vm.user_id
        AND u.status = 'active'
      WHERE vm.venue_id = v.id
        AND vm.status = 'active'
    ) AS managers
  FROM standup_venues v
  WHERE v.status = 'active'
  ORDER BY v.id DESC
");

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $managers = $row['managers'] !== null
    ? json_decode($row['managers'], true)
    : [];

  $is_my_venue_manager = in_array(
    $user_public_id,
    $managers,
    true
  );

  $venues[] = [
    'id' => (string) $row['id'],
    'name' => $row['name'],
    'address' => $row['address'],
    'latlng' => [
      'lat' => $row['latitude'] !== null
        ? (float) $row['latitude']
        : null,
      'lng' => $row['longitude'] !== null
        ? (float) $row['longitude']
        : null,
    ],
    'stations' => $row['stations'] !== null
      ? json_decode($row['stations'], true)
      : [],
    'socialmedia' => [
      'instagram' => $row['instagram'],
      'tiktok' => $row['tiktok'],
      'youtube' => $row['youtube'],
      'x' => $row['x'],
    ],
    'message' => $row['message'],
    'message_for_comedian' => ($is_comedian || $is_my_venue_manager)
      ? $row['message_for_comedian']
      : '',
    'managers' => $managers,
    'status' => $row['status'],
  ];
}


/*
 * コメディアン取得
 */
$comedians = [];

$stmt = $pdo->query("
  SELECT
    sc.id,
    sc.user_id,
    sc.name,
    sc.thumbnail,
    sc.status,
    sc.instagram,
    sc.tiktok,
    sc.youtube,
    sc.x
  FROM standup_comedians AS sc
  INNER JOIN users AS u
    ON u.id = sc.user_id
    AND u.status = 'active'
  WHERE sc.status = 'active'

    AND EXISTS (
      SELECT 1
      FROM user_service_roles AS usr
      WHERE usr.user_id = u.id
        AND usr.service_key = 'standup'
        AND usr.role = 'member'
        AND usr.status = 'active'
    )

    AND EXISTS (
      SELECT 1
      FROM user_service_roles AS usr
      WHERE usr.user_id = u.id
        AND usr.service_key = 'standup'
        AND usr.role = 'comedian'
        AND usr.status = 'active'
    )

  ORDER BY sc.id ASC
");

foreach ($stmt->fetchAll() as $row) {
  $comedians[] = [
    'id' => (string) $row['id'],
    'name' => $row['name'],
    'thumbnail' => $row['thumbnail'],
    'status' => $row['status'],
    'is_me' => $logged_in
      && $user_id !== null
      && (int) $row['user_id'] === (int) $user_id,
    'socialmedia' => [
      'instagram' => $row['instagram'],
      'tiktok' => $row['tiktok'],
      'youtube' => $row['youtube'],
      'x' => $row['x'],
    ],
  ];
}

/*
 * 最新一言取得
 */
$latest_voices = [];

$stmt = $pdo->query('
  SELECT
    sv.id,
    sv.comedian_id,
    sc.user_id,
    sc.name,
    sc.thumbnail,
    sv.text,
    sv.created_at

  FROM standup_comedian_voices AS sv

  INNER JOIN standup_comedians AS sc
    ON sc.id = sv.comedian_id
    AND sc.status = "active"

  INNER JOIN users AS u
    ON u.id = sc.user_id
    AND u.status = "active"

  WHERE EXISTS (
    SELECT 1
    FROM user_service_roles AS usr
    WHERE usr.user_id = u.id
      AND usr.service_key = "standup"
      AND usr.role = "comedian"
      AND usr.status = "active"
  )

  AND EXISTS (
    SELECT 1
    FROM user_service_roles AS usr
    WHERE usr.user_id = u.id
      AND usr.service_key = "standup"
      AND usr.role = "member"
      AND usr.status = "active"
  )

  AND NOT EXISTS (
    SELECT 1
    FROM standup_comedian_voices AS newer
    WHERE newer.comedian_id = sv.comedian_id
      AND (
        newer.created_at > sv.created_at
        OR (
          newer.created_at = sv.created_at
          AND newer.id > sv.id
        )
      )
  )

  ORDER BY
    sv.created_at DESC,
    sv.id DESC

  LIMIT 10
');

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $latest_voices[] = [
    'voice_id' => (int) $row['id'],
    'comedian_id' => (string) $row['comedian_id'],
    'is_me' => $logged_in
      && $user_id !== null
      && (int) $row['user_id'] === (int) $user_id,
    'name' => $row['name'],
    'thumbnail' => $row['thumbnail'],
    'text' => $row['text'],
    'created_at' => $row['created_at'],
  ];
}


echo json_encode([
  'login' => [
    'logged_in' => $logged_in,
    'is_account_limited' => $is_banned,
    'user_public_id' => $user_public_id,
    'nickname' => $nickname,
    'profiles' => $profiles,
  ],
  'comedians' => $comedians,
  'venues' => $venues,
  'events' => $events,
  'event_candidates' => $event_candidates,
  'latest_voices' => $latest_voices,
  'limits' => [
    'upload_max_filesize' => getIniBytes('upload_max_filesize'),
    'post_max_size' => getIniBytes('post_max_size'),
  ],
], JSON_UNESCAPED_UNICODE);