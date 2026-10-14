<?php

/**
 * Standup コメント投稿
 */

// POSTのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  header('Content-Type: application/json; charset=UTF-8');

  echo json_encode([
    'error' => 'POSTでアクセスしてください',
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

// ログインユーザーを取得
$userId = $_SESSION['services'][$serviceKey]['user_id'] ?? null;

if (!$userId) {
  http_response_code(401);
  header('Content-Type: application/json; charset=UTF-8');

  echo json_encode([
    'error' => 'ログインしていません',
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

// リクエストJSONを取得
$input = json_decode(
  file_get_contents('php://input'),
  true
);

if (!is_array($input)) {
  http_response_code(400);
  header('Content-Type: application/json; charset=UTF-8');

  echo json_encode([
    'error' => 'リクエスト形式が不正です',
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

$text = trim((string) ($input['text'] ?? ''));

if ($text === '') {
  http_response_code(400);
  header('Content-Type: application/json; charset=UTF-8');

  echo json_encode([
    'error' => 'コメント本文がありません',
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

// DB接続
$pdo = createDatabaseConnection();

// Standupのcomedianロールを確認
$stmt = $pdo->prepare('
  SELECT
    u.public_id
  FROM users AS u

  INNER JOIN user_service_roles AS usr
    ON usr.user_id = u.id

  WHERE u.id = ?
    AND u.status = ?
    AND usr.service_key = ?
    AND usr.role = ?
    AND usr.status = ?
');

$stmt->execute([
  $userId,
  'active',
  'standup',
  'comedian',
  'active',
]);

$user = $stmt->fetch();

if (!$user) {
  http_response_code(403);
  header('Content-Type: application/json; charset=UTF-8');

  echo json_encode([
    'error' => 'コメントを投稿できません',
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

// コメントを保存
$stmt = $pdo->prepare('
  INSERT INTO standup_comments (
    user_id,
    text
  ) VALUES (?, ?)
');

$stmt->execute([
  $userId,
  $text,
]);

$commentId = $pdo->lastInsertId();

// 作成日時を取得
$stmt = $pdo->prepare('
  SELECT created_at
  FROM standup_comments
  WHERE id = ?
');

$stmt->execute([
  $commentId,
]);

$comment = $stmt->fetch();

header('Content-Type: application/json; charset=UTF-8');

echo json_encode([
  'success' => true,
  'comment' => [
    'user_public_id' => $user['public_id'],
    'text' => $text,
    'created_at' => $comment['created_at'],
  ],
], JSON_UNESCAPED_UNICODE);

exit;