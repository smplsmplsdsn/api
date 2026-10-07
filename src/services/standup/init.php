<?php

header('Content-Type: application/json; charset=UTF-8');

$pdo = createDatabaseConnection();

// ログインユーザー
$user_id = $_SESSION['services']['standup']['user_id'] ?? null;

$logged_in = false;
$user_public_id = '';
$nickname = '';
$profiles = [];

$public_id = null;
$is_comedian = false;

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

    $is_comedian = in_array('comedian', $profiles, true)
      && $comedian_record_active;
  }
}

// イベント取得
$stmt = $pdo->query('
  SELECT
    se.id,
    se.creator_public_id,
    se.type,
    se.name,
    se.message,
    se.message_for_comedian,
    se.image,
    se.status,
    se.confirmed_candidate_id,

    sec.id AS candidate_id,
    sec.venue_id,
    sec.open_at,
    sec.start_at,
    sec.fee

  FROM standup_events AS se

  LEFT JOIN standup_event_candidates AS sec
    ON sec.event_id = se.id

  ORDER BY
    se.id DESC,
    sec.id ASC
');

$rows = $stmt->fetchAll();

// 現在日時
$now = new DateTime();

// イベント
$events = [];

// 過去イベント
$past_events = [];

// イベントで使用する会場ID
$venue_ids = [];

// イベントで使用する芸人ID
$comedian_ids = [];

// 芸人ごとの登録イベント
$registered_candidates = [];

foreach ($rows as $row) {
  $event_id = (int) $row['id'];

  $event_data = [
    'id' => $event_id,
    'creator_public_id' => $row['creator_public_id'],
    'type' => $row['type'],
    'name' => $row['name'],
    'message' => $row['message'],
    'message_for_comedian' => $is_comedian
      ? $row['message_for_comedian']
      : '',
    'image' => $row['image'],
    'status' => $row['status'],
    'confirmed_candidate_id' => $row['confirmed_candidate_id']
      !== null
      ? (int) $row['confirmed_candidate_id']
      : null,
    'candidates' => [],
  ];

  // 候補が存在しない場合
  if ($row['candidate_id'] === null) {
    continue;
  }

  // 日時判定
  $candidate_datetime = null;

  if ($row['start_at'] !== null) {
    $candidate_datetime = new DateTime($row['start_at']);
  } elseif ($row['open_at'] !== null) {
    $candidate_datetime = new DateTime($row['open_at']);
  }

  $candidate_id = (int) $row['candidate_id'];

  // 日時未定の場合
  if ($candidate_datetime === null) {
    // 作成者本人かつ comedian のみ表示
    if (
      $public_id === null ||
      $row['creator_public_id'] !== $public_id ||
      !$is_comedian
    ) {
      continue;
    }

    if (!isset($events[$event_id])) {
      $events[$event_id] = $event_data;
    }

    $events[$event_id]['candidates'][$candidate_id] = [
      'id' => $candidate_id,
      'venue_id' => $row['venue_id'] !== null
        ? (string) $row['venue_id']
        : null,
      'open_at' => $row['open_at'],
      'start_at' => $row['start_at'],
      'fee' => $row['fee'],
      'comedian_ids' => [],
    ];

  // 過去の場合
  } elseif ($candidate_datetime <= $now) {
    if (!isset($past_events[$event_id])) {
      $past_events[$event_id] = $event_data;
    }

    $past_events[$event_id]['candidates'][$candidate_id] = [
      'id' => $candidate_id,
      'venue_id' => $row['venue_id'] !== null
        ? (string) $row['venue_id']
        : null,
      'open_at' => $row['open_at'],
      'start_at' => $row['start_at'],
      'fee' => $row['fee'],
      'comedian_ids' => [],
    ];

  // 未来の場合
  } else {
    if (!isset($events[$event_id])) {
      $events[$event_id] = $event_data;
    }

    $events[$event_id]['candidates'][$candidate_id] = [
      'id' => $candidate_id,
      'venue_id' => $row['venue_id'] !== null
        ? (string) $row['venue_id']
        : null,
      'open_at' => $row['open_at'],
      'start_at' => $row['start_at'],
      'fee' => $row['fee'],
      'comedian_ids' => [],
    ];
  }

  // 会場IDを記録
  if ($row['venue_id'] !== null) {
    $venue_ids[(string) $row['venue_id']] = true;
  }
}

// 候補に出演する芸人を取得
$candidate_id_list = [];

foreach ($events as $event) {
  foreach ($event['candidates'] as $candidate) {
    $candidate_id_list[] = $candidate['id'];
  }
}

foreach ($past_events as $event) {
  foreach ($event['candidates'] as $candidate) {
    $candidate_id_list[] = $candidate['id'];
  }
}

$candidate_id_list = array_values(array_unique($candidate_id_list));

if (count($candidate_id_list) > 0) {
  $placeholders = implode(
    ',',
    array_fill(0, count($candidate_id_list), '?')
  );

  $stmt = $pdo->prepare("
    SELECT
      secc.candidate_id,
      secc.comedian_id
    FROM standup_event_candidate_comedians AS secc
    INNER JOIN standup_comedians AS sc
      ON sc.id = secc.comedian_id
    INNER JOIN users AS u
      ON u.id = sc.user_id
    WHERE secc.candidate_id IN ($placeholders)
      AND sc.status = 'active'
      AND u.status = 'active'
    ORDER BY
      secc.candidate_id ASC,
      secc.id ASC
  ");

  $stmt->execute($candidate_id_list);

  foreach ($stmt->fetchAll() as $row) {
    $candidate_id = (int) $row['candidate_id'];
    $comedian_id = (string) $row['comedian_id'];

    // events
    foreach ($events as &$event) {
      if (isset($event['candidates'][$candidate_id])) {
        $event['candidates'][$candidate_id]['comedian_ids'][] = $comedian_id;
        $comedian_ids[$comedian_id] = true;

        if (!isset($registered_candidates[$comedian_id])) {
          $registered_candidates[$comedian_id] = [];
        }

        $registered_candidates[$comedian_id][] = (string) $candidate_id;

        break;
      }
    }
    unset($event);

    // past_events
    foreach ($past_events as &$event) {
      if (isset($event['candidates'][$candidate_id])) {
        $event['candidates'][$candidate_id]['comedian_ids'][] = $comedian_id;
        $comedian_ids[$comedian_id] = true;

        if (!isset($registered_candidates[$comedian_id])) {
          $registered_candidates[$comedian_id] = [];
        }

        $registered_candidates[$comedian_id][] = (string) $candidate_id;

        break;
      }
    }
    unset($event);
  }
}

// 候補が1件も残っていないイベントを削除
$events = array_filter($events, function ($event) {
  return count($event['candidates']) > 0;
});

$past_events = array_filter($past_events, function ($event) {
  return count($event['candidates']) > 0;
});

// イベント・候補を通常の配列へ
foreach ($events as &$event) {
  $event['candidates'] = array_values($event['candidates']);
}
unset($event);

foreach ($past_events as &$event) {
  $event['candidates'] = array_values($event['candidates']);
}
unset($event);

$events = array_values($events);
$past_events = array_values($past_events);

// 会場取得
$venues = [];

if (count($venue_ids) > 0) {
  $placeholders = implode(
    ',',
    array_fill(0, count($venue_ids), '?')
  );

  $stmt = $pdo->prepare("
    SELECT
      id,
      name,
      address,
      latitude,
      longitude,
      stations,
      instagram,
      tiktok,
      youtube,
      x,
      message,
      message_for_comedian,
      status
    FROM standup_venues
    WHERE id IN ($placeholders)
    ORDER BY id ASC
  ");

  $stmt->execute(array_keys($venue_ids));

  foreach ($stmt->fetchAll() as $row) {
    $venues[] = [
      'id' => (int) $row['id'],
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
      'message_for_comedian' => $is_comedian
        ? $row['message_for_comedian']
        : '',
      'status' => $row['status'],
    ];
  }
}

// 芸人取得
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
  WHERE sc.status = 'active'
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
    'registered_candidates' => $registered_candidates[(string) $row['id']] ?? [],
    'socialmedia' => [
      'instagram' => $row['instagram'],
      'tiktok' => $row['tiktok'],
      'youtube' => $row['youtube'],
      'x' => $row['x'],
    ],
  ];
}

// お知らせ取得
$notices = [];

$stmt = $pdo->query('
  SELECT
    date,
    link,
    text
  FROM standup_notices
  WHERE status = "active"
  ORDER BY date DESC, id DESC
');

foreach ($stmt->fetchAll() as $row) {
  $date = new DateTime($row['date']);

  $notices[] = [
    'date' => $date->format('Y.n.j'),
    'link' => $row['link'],
    'text' => $row['text'],
  ];
}

// 最新コメント取得
$latest_comments = [];

$stmt = $pdo->query('
  SELECT
    c.id,
    c.comedian_id,
    u.public_id,
    sc.name,
    sc.thumbnail,
    c.text,
    c.created_at

  FROM (
    SELECT
      id,
      user_id,
      comedian_id,
      text,
      created_at,
      ROW_NUMBER() OVER (
        PARTITION BY user_id
        ORDER BY created_at DESC, id DESC
      ) AS rn
    FROM standup_comments
    WHERE comedian_id IS NOT NULL
  ) AS c

  INNER JOIN users AS u
    ON u.id = c.user_id

  INNER JOIN standup_comedians AS sc
    ON sc.id = c.comedian_id

  WHERE c.rn = 1
    AND u.status = "active"
    AND sc.status = "active"

    AND EXISTS (
      SELECT 1
      FROM user_service_roles AS usr
      WHERE usr.user_id = c.user_id
        AND usr.service_key = "standup"
        AND usr.role = "comedian"
        AND usr.status = "active"
    )

  ORDER BY c.created_at DESC, c.id DESC

  LIMIT 5
');

foreach ($stmt->fetchAll() as $row) {
  $latest_comments[] = [
    'comment_id' => (int) $row['id'],
    'comedian_id' => (string) $row['comedian_id'],
    'user_public_id' => $row['public_id'],
    'name' => $row['name'],
    'thumbnail' => $row['thumbnail'],
    'text' => $row['text'],
    'created_at' => $row['created_at'],
  ];
}

echo json_encode([
  'login' => [
    'logged_in' => $logged_in,
    'user_public_id' => $user_public_id,
    'nickname' => $nickname,
    'profiles' => $profiles,
  ],
  'comedians' => $comedians,
  'venues' => $venues,
  'events' => $events,
  'past_events' => $past_events,
  'notices' => $notices,
  'latest_comments' => $latest_comments,
], JSON_UNESCAPED_UNICODE);