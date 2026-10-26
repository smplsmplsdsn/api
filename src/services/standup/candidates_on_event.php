<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../app/config/config.php';

// エラー返却
function eventCandidateComediansError(string $message, int $status = 400): void {
  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

// IDの形式を確認
function isValidEventCandidateComediansId($id): bool {
  if (!is_string($id) && !is_int($id)) {
    return false;
  }

  return ctype_digit((string)$id)
    && (int)$id > 0;
}

try {

  // GETのみ許可
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    eventCandidateComediansError('Method not allowed', 405);
  }

  $pdo = createDatabaseConnection();

  // リクエストのバリデーション
  $event_id = $_GET['event_id'] ?? null;

  if (!isValidEventCandidateComediansId($event_id)) {
    eventCandidateComediansError('Invalid event id', 400);
  }

  $event_id = (int)$event_id;

  // 参加者情報の取得
  $stmt = $pdo->prepare('
    SELECT
      c.id AS candidate_id,
      ac.id AS comedian_id
    FROM standup_event_candidates c
    LEFT JOIN standup_event_candidate_comedians ecc
      ON ecc.candidate_id = c.id
    LEFT JOIN (
      SELECT DISTINCT
        sc.id
      FROM standup_comedians sc
      INNER JOIN users u
        ON u.id = sc.user_id
      INNER JOIN user_service_roles r
        ON r.user_id = u.id
        AND r.service_key = "standup"
        AND r.role = "comedian"
        AND r.status = "active"
      WHERE sc.status = "active"
        AND u.status = "active"
    ) ac
      ON ac.id = ecc.comedian_id
    WHERE c.event_id = :event_id
    ORDER BY c.id, ac.id
  ');

  $stmt->execute([
    ':event_id' => $event_id
  ]);


  $candidates = [];

  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $candidate_id = (string)$row['candidate_id'];

    // 参加者がいない候補も空配列で保持
    $candidates[$candidate_id] ??= [];

    if ($row['comedian_id'] !== null) {
      $candidates[$candidate_id][] = (string)$row['comedian_id'];
    }
  }

  echo json_encode([
    'success' => true,
    'event_id' => (string)$event_id,
    'candidates' => (object)$candidates
  ], JSON_UNESCAPED_UNICODE);

  exit;

} catch (Throwable $e) {
  error_log((string)$e);
  eventCandidateComediansError('Internal server error', 500);
}