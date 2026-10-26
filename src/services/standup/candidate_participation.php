<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/functions/permissions.php';

// エラー返却
function eventCandidateComedianError(string $message, int $status = 400): void {
  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

// IDの形式を確認
function isValidEventCandidateComedianId($id): bool {
  if (!is_string($id) && !is_int($id)) {
    return false;
  }

  return ctype_digit((string)$id)
    && (int)$id > 0;
}

try {

  // POSTのみ許可
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    eventCandidateComedianError('Method not allowed', 405);
  }

  $pdo = createDatabaseConnection();

  // ログイン確認
  $user_id = $_SESSION['services']['standup']['user_id'] ?? null;

  if (!$user_id) {
    eventCandidateComedianError('Unauthorized', 401);
  }

  // ユーザーの有効性を確認
  $stmt = $pdo->prepare('
    SELECT id
    FROM users
    WHERE id = :id
      AND status = "active"
    LIMIT 1
  ');

  $stmt->execute([':id' => $user_id]);

  $user_id = $stmt->fetchColumn();

  if (!$user_id) {
    eventCandidateComedianError('Unauthorized', 401);
  }

  $user_id = (int)$user_id;

  // コメディアンの権限確認
  $comedian_id = getActiveComedianId(
    $pdo,
    $user_id
  );

  if ($comedian_id === null) {
    eventCandidateComedianError('Forbidden', 403);
  }

  // リクエストの取得
  $input = json_decode(
    file_get_contents('php://input'),
    true
  );

  if (!is_array($input)) {
    eventCandidateComedianError('Invalid request body', 400);
  }

  // action
  $action = $input['action'] ?? null;

  if (
    !is_string($action)
    || !in_array($action, ['join', 'cancel'], true)
  ) {
    eventCandidateComedianError('Invalid action' . $action , 400);
  }

  // candidate_id
  $candidate_id = $input['candidate_id'] ?? null;

  if (!isValidEventCandidateComedianId($candidate_id)) {
    eventCandidateComedianError('Invalid candidate id', 400);
  }

  $candidate_id = (int)$candidate_id;

  // 候補の存在確認
  $stmt = $pdo->prepare('
    SELECT id
    FROM standup_event_candidates
    WHERE id = :id
    LIMIT 1
  ');

  $stmt->execute([':id' => $candidate_id]);

  if ($stmt->fetchColumn() === false) {
    eventCandidateComedianError('Candidate not found', 404);
  }


  if ($action === 'join') {

    // 参加表明
    $stmt = $pdo->prepare('
      INSERT INTO standup_event_candidate_comedians (
        candidate_id,
        comedian_id
      ) VALUES (
        :candidate_id,
        :comedian_id
      )
      ON DUPLICATE KEY UPDATE
        id = id
    ');

    $stmt->execute([
      ':candidate_id' => $candidate_id,
      ':comedian_id' => $comedian_id
    ]);

  } else {

    // 参加キャンセル
    $stmt = $pdo->prepare('
      DELETE FROM standup_event_candidate_comedians
      WHERE candidate_id = :candidate_id
        AND comedian_id = :comedian_id
    ');

    $stmt->execute([
      ':candidate_id' => $candidate_id,
      ':comedian_id' => $comedian_id
    ]);
  }

  echo json_encode([
    'success' => true,
    'action' => $action,
    'candidate_id' => (string)$candidate_id,
    'comedian_id' => (string)$comedian_id
  ], JSON_UNESCAPED_UNICODE);

  exit;

} catch (Throwable $e) {

  error_log((string)$e);

  // NOTE 候補が削除された場合に処理が走ることがあるが、その場合は500エラーになる
  eventCandidateComedianError('Internal server error', 500);
}
