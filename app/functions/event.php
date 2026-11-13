
<?php

function getStandupEvents(
  PDO $pdo,
  ?string $public_id,
  bool $is_comedian,
  bool $is_venue_manager
): array {

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

  $events = [];

  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $event_id = (int) $row['id'];

    $events[$event_id] = [
      'id' => (string) $event_id,
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
      'confirmed_candidate_id' => $row['confirmed_candidate_id'] !== null
        ? (string) $row['confirmed_candidate_id']
        : null,
    ];
  }

  /*
   * 候補日情報取得
   */
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

  // 今日
  $today = new DateTime('today');

  $event_candidates = [];

  foreach ($candidate_rows as $row) {
    $event_id = (string) $row['event_id'];
    $candidate_id = (int) $row['id'];

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

      if (
        $events[$event_id]['creator_public_id'] !== $public_id ||
        (!$is_comedian && !$is_venue_manager)
      ) {
        continue;
      }

    // 過去の場合は取得対象外
    } elseif ($candidate_datetime < $today) {
      continue;
    }

    $event_candidates[$candidate_id] = [
      'id' => (string) $candidate_id,
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

  /*
   * 候補日に出演するコメディアン取得
   */
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
    ");

    $stmt->execute($candidate_id_list);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $candidate_id = (int) $row['candidate_id'];

      if (isset($event_candidates[$candidate_id])) {
        $event_candidates[$candidate_id]['comedian_ids'][] =
          (string) $row['comedian_id'];
      }
    }
  }

  return [
    'events' => array_values($events),
    'event_candidates' => array_values($event_candidates),
  ];
}
