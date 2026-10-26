<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/functions/permissions.php';
require_once __DIR__ . '/../../../app/functions/stripHtmlFromJsonValue.php';

// エラー返却
function eventError(string $message, int $status = 400): void {
  global $pdo;

  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

// IDの形式を確認
function isValidEventId($id): bool {
  if (!is_string($id) && !is_int($id)) {
    return false;
  }

  return ctype_digit((string)$id)
    && (int)$id > 0;
}

// 文字列のHTMLタグを除去
function sanitizeEventString($value, string $field): string {
  if (!is_string($value)) {
    eventError("Invalid {$field}", 400);
  }

  $value = stripHtmlFromJsonValue($value);

  if (!is_string($value)) {
    eventError("Invalid {$field}", 400);
  }

  return $value;
}

// DATETIMEを検証
function normalizeEventDateTime($value, string $field): ?string {
  if ($value === null || $value === '') {
    return null;
  }

  if (!is_string($value)) {
    eventError("Invalid {$field}", 400);
  }

  $value = trim($value);

  if ($value === '') {
    return null;
  }

  $date = DateTime::createFromFormat(
    '!Y-m-d H:i:s',
    $value
  );

  $errors = DateTime::getLastErrors();

  if (
    !$date
    || ($errors !== false && (
      $errors['warning_count'] > 0
      || $errors['error_count'] > 0
    ))
    || $date->format('Y-m-d H:i:s') !== $value
  ) {
    eventError("Invalid {$field}", 400);
  }

  return $value;
}

// イベント情報を取得
function getEventData(PDO $pdo, int $event_id): ?array {
  $stmt = $pdo->prepare('
    SELECT
      id,
      creator_public_id,
      type,
      name,
      message,
      message_for_comedian,
      image,
      status,
      confirmed_candidate_id,
      created_at,
      updated_at
    FROM standup_events
    WHERE id = :id
    LIMIT 1
  ');

  $stmt->execute([':id' => $event_id]);

  $event = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$event) {
    return null;
  }

  $event['id'] = (string)$event['id'];

  $event['confirmed_candidate_id'] =
    $event['confirmed_candidate_id'] !== null
      ? (string)$event['confirmed_candidate_id']
      : null;

  // 候補情報を取得
  $stmt = $pdo->prepare('
    SELECT
      id,
      event_id,
      venue_id,
      open_at,
      start_at,
      fee
    FROM standup_event_candidates
    WHERE event_id = :event_id
    ORDER BY id ASC
  ');

  $stmt->execute([':event_id' => $event_id]);

  $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($candidates as &$candidate) {
    $candidate['id'] = (string)$candidate['id'];
    $candidate['event_id'] = (string)$candidate['event_id'];

    $candidate['venue_id'] =
      $candidate['venue_id'] !== null
        ? (string)$candidate['venue_id']
        : null;
  }

  unset($candidate);

  $event['candidates'] = $candidates;

  return $event;
}

try {

  // POSTのみ許可
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    eventError('Method not allowed', 405);
  }

  $pdo = createDatabaseConnection();

  // ログインユーザーを確認
  $user_id = $_SESSION['services']['standup']['user_id'] ?? null;

  if (!$user_id) {
    eventError('Unauthorized', 401);
  }

  // ユーザーの有効性と公開IDを確認
  $stmt = $pdo->prepare('
    SELECT public_id
    FROM users
    WHERE id = :id
      AND status = "active"
    LIMIT 1
  ');

  $stmt->execute([':id' => $user_id]);

  $user_public_id = $stmt->fetchColumn();

  if (!$user_public_id) {
    eventError('Unauthorized', 401);
  }

  $user_id = (int)$user_id;

  // 新規作成・編集を判定
  $id = $_POST['id'] ?? null;
  $is_new = $id === null || $id === '';

  if (!$is_new && !isValidEventId($id)) {
    eventError('Invalid event id', 400);
  }

  $event_id = $is_new ? null : (int)$id;


  /*
   * リクエストのバリデーション
   */

  // type
  $type = sanitizeEventString(
    $_POST['type'] ?? '',
    'type'
  );

  $type = trim($type);

  $allowed_types = [
    'openmic',
    'live'
  ];

  if (!in_array($type, $allowed_types, true)) {
    eventError('Invalid event type', 400);
  }


  // status
  $status = sanitizeEventString(
    $_POST['status'] ?? '',
    'status'
  );

  $status = trim($status);

  $allowed_statuses = [
    'recruiting',
    'limited',
    'closed'
  ];

  if (!in_array($status, $allowed_statuses, true)) {
    eventError('Invalid event status', 400);
  }


  // name
  $name = sanitizeEventString(
    $_POST['name'] ?? '',
    'name'
  );

  $name = trim($name);

  if (mb_strlen($name) > 255) {
    eventError('Event name is too long', 400);
  }

  // message
  $message = sanitizeEventString(
    $_POST['message'] ?? '',
    'message'
  );

  // message_for_comedian
  $message_for_comedian = sanitizeEventString(
    $_POST['message_for_comedian'] ?? '',
    'message_for_comedian'
  );

  // image
  $image = sanitizeEventString(
    $_POST['image'] ?? '',
    'image'
  );

  if (mb_strlen($image) > 500) {
    eventError('Event image path is too long', 400);
  }

  /*
   * candidates
   */

  if (
    !isset($_POST['candidates'])
    || !is_string($_POST['candidates'])
  ) {
    eventError('Candidates are required', 400);
  }


  $candidates_json = $_POST['candidates'];

  if ($candidates_json === '') {
    $candidates_json = '[]';
  }

  $candidates = json_decode($candidates_json);

  if (
    json_last_error() !== JSON_ERROR_NONE
    || !is_array($candidates)
  ) {
    eventError('Invalid candidates JSON', 400);
  }

  $normalized_candidates = [];
  $submitted_candidate_ids = [];

  foreach ($candidates as $candidate) {

    if (!$candidate instanceof stdClass) {
      eventError('Invalid candidate data', 400);
    }

    // 候補ID
    $candidate_id = $candidate->id ?? null;

    if ($candidate_id === '') {
      $candidate_id = null;
    }

    if (
      $candidate_id !== null
      && !isValidEventId($candidate_id)
    ) {
      eventError('Invalid candidate id', 400);
    }

    $candidate_id = $candidate_id !== null
      ? (int)$candidate_id
      : null;

    // 新規作成時に既存候補IDは指定できない
    if ($is_new && $candidate_id !== null) {
      eventError('Invalid candidate id', 400);
    }

    // 候補IDの重複を確認
    if ($candidate_id !== null) {
      $key = (string)$candidate_id;

      if (isset($submitted_candidate_ids[$key])) {
        eventError('Duplicate candidate id', 400);
      }

      $submitted_candidate_ids[$key] = true;
    }

    // 会場ID
    $venue_id = $candidate->venue_id ?? null;

    if (
      $venue_id !== null
      && $venue_id !== ''
      && !isValidEventId($venue_id)
    ) {
      eventError('Invalid venue id', 400);
    }

    $venue_id = (
      $venue_id === null
      || $venue_id === ''
    )
      ? null
      : (int)$venue_id;

    // 開場日時
    $open_at = normalizeEventDateTime(
      $candidate->open_at ?? null,
      'open_at'
    );

    // 開演日時
    $start_at = normalizeEventDateTime(
      $candidate->start_at ?? null,
      'start_at'
    );

    // 料金
    $fee = sanitizeEventString(
      $candidate->fee ?? '',
      'fee'
    );

    $normalized_candidates[] = [
      'id' => $candidate_id,
      'venue_id' => $venue_id,
      'open_at' => $open_at,
      'start_at' => $start_at,
      'fee' => $fee
    ];
  }


  /*
   * 新規作成権限
   */

  if ($is_new) {

    $is_active_comedian = getActiveComedianId(
      $pdo,
      $user_id
    ) !== null;

    $is_venue_manager = hasActiveRole(
      $pdo,
      $user_id,
      'standup',
      'venue_manager'
    );

    if (!$is_active_comedian && !$is_venue_manager) {
      eventError('Forbidden', 403);
    }
  }


  /*
   * DB保存
   */

  $pdo->beginTransaction();

  if ($is_new) {

    // 新規イベント
    $stmt = $pdo->prepare('
      INSERT INTO standup_events (
        creator_public_id,
        type,
        name,
        message,
        message_for_comedian,
        image,
        status
      ) VALUES (
        :creator_public_id,
        :type,
        :name,
        :message,
        :message_for_comedian,
        :image,
        :status
      )
    ');

    $stmt->execute([
      ':creator_public_id' => $user_public_id,
      ':type' => $type,
      ':name' => $name,
      ':message' => $message,
      ':message_for_comedian' => $message_for_comedian,
      ':image' => $image,
      ':status' => $status
    ]);

    $event_id = (int)$pdo->lastInsertId();

  } else {

    // 編集対象のイベントをロックして取得
    $stmt = $pdo->prepare('
      SELECT creator_public_id
      FROM standup_events
      WHERE id = :id
      LIMIT 1
      FOR UPDATE
    ');

    $stmt->execute([':id' => $event_id]);

    $creator_public_id = $stmt->fetchColumn();

    if ($creator_public_id === false) {
      eventError('Event not found', 404);
    }

    // 作成者本人か確認
    if ($creator_public_id !== $user_public_id) {
      eventError('You do not have permission to edit this event.', 403);
    }

    // 既存候補をロックして取得
    $stmt = $pdo->prepare('
      SELECT id
      FROM standup_event_candidates
      WHERE event_id = :event_id
      FOR UPDATE
    ');

    $stmt->execute([':event_id' => $event_id]);

    $existing_candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $existing_candidate_ids = [];
    $existing_candidate_map = [];

    foreach ($existing_candidates as $candidate) {
      $candidate_id = (string)$candidate['id'];

      $existing_candidate_ids[] = $candidate_id;

      $existing_candidate_map[$candidate_id] = true;
    }

    // 確定候補を一旦NULLにして外部キー制約を回避
    $stmt = $pdo->prepare('
      UPDATE standup_events
      SET
        confirmed_candidate_id = NULL,
        type = :type,
        name = :name,
        message = :message,
        message_for_comedian = :message_for_comedian,
        image = :image,
        status = :status
      WHERE id = :id
    ');

    $stmt->execute([
      ':type' => $type,
      ':name' => $name,
      ':message' => $message,
      ':message_for_comedian' => $message_for_comedian,
      ':image' => $image,
      ':status' => $status,
      ':id' => $event_id
    ]);
  }


  /*
   * 会場の存在・ステータス確認
   */

  $checked_venues = [];

  foreach ($normalized_candidates as $candidate) {

    $venue_id = $candidate['venue_id'];

    if ($venue_id === null) {
      continue;
    }

    if (isset($checked_venues[$venue_id])) {
      continue;
    }

    $stmt = $pdo->prepare('
      SELECT id
      FROM standup_venues
      WHERE id = :id
      LIMIT 1
      FOR UPDATE
    ');

    $stmt->execute([':id' => $venue_id]);

    $found_venue_id = $stmt->fetchColumn();

    if ($found_venue_id === false) {
      eventError('Venue not found', 400);
    }

    $checked_venues[$venue_id] = true;
  }


  /*
   * 候補の追加・更新
   */

  $retained_candidate_ids = [];

  foreach ($normalized_candidates as $candidate) {

    if ($candidate['id'] === null) {

      // 新規候補
      $stmt = $pdo->prepare('
        INSERT INTO standup_event_candidates (
          event_id,
          venue_id,
          open_at,
          start_at,
          fee
        ) VALUES (
          :event_id,
          :venue_id,
          :open_at,
          :start_at,
          :fee
        )
      ');

      $stmt->execute([
        ':event_id' => $event_id,
        ':venue_id' => $candidate['venue_id'],
        ':open_at' => $candidate['open_at'],
        ':start_at' => $candidate['start_at'],
        ':fee' => $candidate['fee']
      ]);

    } else {

      // 既存候補
      $candidate_id = $candidate['id'];

      // このイベントに属する候補か確認
      if (!isset($existing_candidate_map[(string)$candidate_id])) {
        eventError('Invalid candidate id', 400);
      }

      // 参加表明の有無にかかわらずすべて更新
      $stmt = $pdo->prepare('
        UPDATE standup_event_candidates
        SET
          venue_id = :venue_id,
          open_at = :open_at,
          start_at = :start_at,
          fee = :fee
        WHERE id = :id
          AND event_id = :event_id
      ');

      $stmt->execute([
        ':venue_id' => $candidate['venue_id'],
        ':open_at' => $candidate['open_at'],
        ':start_at' => $candidate['start_at'],
        ':fee' => $candidate['fee'],
        ':id' => $candidate_id,
        ':event_id' => $event_id
      ]);

      $retained_candidate_ids[] = (string)$candidate_id;

    }
  }


  /*
   * 不要になった候補を削除
   */

  if (!$is_new) {

    foreach ($existing_candidate_ids as $existing_id) {

      if (in_array(
        $existing_id,
        $retained_candidate_ids,
        true
      )) {
        continue;
      }

      $stmt = $pdo->prepare('
        DELETE FROM standup_event_candidates
        WHERE id = :id
          AND event_id = :event_id
      ');

      $stmt->execute([
        ':id' => $existing_id,
        ':event_id' => $event_id
      ]);
    }
  }


  /*
   * 候補数に応じて確定候補を自動設定
   */

  $stmt = $pdo->prepare('
    SELECT id
    FROM standup_event_candidates
    WHERE event_id = :event_id
    ORDER BY id ASC
    LIMIT 2
  ');

  $stmt->execute([':event_id' => $event_id]);

  $remaining_candidate_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

  $confirmed_candidate_id = count($remaining_candidate_ids) === 1
    ? (int)$remaining_candidate_ids[0]
    : null;

  $stmt = $pdo->prepare('
    UPDATE standup_events
    SET confirmed_candidate_id = :candidate_id
    WHERE id = :event_id
  ');

  $stmt->execute([
    ':candidate_id' => $confirmed_candidate_id,
    ':event_id' => $event_id
  ]);


  /*
   * 保存完了
   */

  // 保存後のイベント情報を取得
  $event = getEventData($pdo, $event_id);

  if (!$event) {
    eventError('Failed to retrieve event', 500);
  }

  $pdo->commit();

  // 成功レスポンス
  echo json_encode([
    'success' => true,
    'id' => (string)$event_id,
    'event' => $event
  ], JSON_UNESCAPED_UNICODE);

  exit;

} catch (Throwable $e) {

  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  error_log((string)$e);

  eventError('Internal server error', 500);
}
