<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../app/config/config.php';

// エラー返却
function venueEventCandidatesError(string $message, int $status = 400): void {
  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

// IDの形式を確認
function isValidVenueEventCandidatesId($id): bool {
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
    venueEventCandidatesError('Method not allowed', 405);
  }

  $pdo = createDatabaseConnection();

  // リクエストのバリデーション
  $venue_id = $_GET['venue_id'] ?? null;

  if (!isValidVenueEventCandidatesId($venue_id)) {
    venueEventCandidatesError('Invalid venue id', 400);
  }

  $venue_id = (int)$venue_id;

  $stmt = $pdo->prepare('
    SELECT id
    FROM standup_event_candidates
    WHERE venue_id = :venue_id
    ORDER BY id
  ');

  $stmt->execute([
    ':venue_id' => $venue_id
  ]);

  $candidate_ids = array_map(
    'strval',
    $stmt->fetchAll(PDO::FETCH_COLUMN)
  );

  echo json_encode([
    'success' => true,
    'venue_id' => (string)$venue_id,
    'candidates' => [
      (string)$venue_id => $candidate_ids
    ]
  ], JSON_UNESCAPED_UNICODE);

  exit;

} catch (Throwable $e) {
  error_log((string)$e);
  venueEventCandidatesError('Internal server error', 500);
}