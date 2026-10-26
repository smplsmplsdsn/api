<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../app/config/config.php';

// エラー返却
function comedianEventCandidatesError(string $message, int $status = 400): void {
  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

// IDの形式を確認
function isValidComedianEventCandidatesId($id): bool {
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
    comedianEventCandidatesError('Method not allowed', 405);
  }

  $pdo = createDatabaseConnection();

  // リクエストのバリデーション
  $comedian_id = $_GET['comedian_id'] ?? null;

  if (!isValidComedianEventCandidatesId($comedian_id)) {
    comedianEventCandidatesError('Invalid comedian id', 400);
  }

  $comedian_id = (int)$comedian_id;

  // 参加候補情報の取得
  $stmt = $pdo->prepare('
    SELECT
      c.id
    FROM standup_event_candidates c
    INNER JOIN standup_event_candidate_comedians ecc
      ON ecc.candidate_id = c.id
    INNER JOIN standup_comedians sc
      ON sc.id = ecc.comedian_id
      AND sc.status = "active"
    INNER JOIN users u
      ON u.id = sc.user_id
      AND u.status = "active"
    INNER JOIN user_service_roles r
      ON r.user_id = u.id
      AND r.service_key = "standup"
      AND r.role = "comedian"
      AND r.status = "active"
    WHERE ecc.comedian_id = :comedian_id
  ');

  $stmt->execute([
    ':comedian_id' => $comedian_id
  ]);

  $candidate_ids = array_map(
    'strval',
    $stmt->fetchAll(PDO::FETCH_COLUMN)
  );

  echo json_encode([
    'success' => true,
    'comedian_id' => (string)$comedian_id,
    'candidates' => [
      (string)$comedian_id => $candidate_ids
    ]
  ], JSON_UNESCAPED_UNICODE);

  exit;

} catch (Throwable $e) {
  error_log((string)$e);
  comedianEventCandidatesError('Internal server error', 500);
}