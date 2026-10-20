<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/domains.php';
require_once __DIR__ . '/../../app/functions/sendMail.php';

function sendError(string $message, int $status = 400) {
  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

// POSTのみ受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  sendError('許可されていないリクエストです。', 405);
}

// JSONリクエストを取得
$input = json_decode(file_get_contents('php://input'), true);

// JSON形式を確認
if (!is_array($input)) {
  sendError('リクエスト形式が正しくありません。', 400);
}

// サービスキーを取得
$service_key = $input['service_key'] ?? '';

// サービスキーを確認
if (
  !is_string($service_key) ||
  !in_array($service_key, $allowedServices, true) ||
  !isset($serviceNames[$service_key])
) {
  sendError('対象のサービスが正しくありません。', 400);
}

$service_name = $serviceNames[$service_key];

// ご意見を取得
$message = trim($input['message'] ?? '');

// 入力チェック
$message = $input['message'] ?? '';

if (!is_string($message)) {
  sendError('ご意見の形式が正しくありません。', 400);
}

$message = trim($message);

if ($message === '') {
  sendError('ご意見を入力してください。', 400);
}

if (mb_strlen($message) > 5000) {
  sendError('ご意見は5000文字以内で入力してください。', 400);
}

// メール本文
$body = <<<TEXT
ご意見箱に新しい投稿がありました。

【ご意見】
{$message}
TEXT;

// メール送信
$success = sendMail(
  $env,
  "{$service_name} ご意見箱",
  $body
);

if (!$success) {
  sendError('送信に失敗しました。時間をおいて再度お試しください。', 500);
}

echo json_encode([
  'success' => true,
  'message' => 'ご意見を送信しました。ありがとうございます！',
], JSON_UNESCAPED_UNICODE);