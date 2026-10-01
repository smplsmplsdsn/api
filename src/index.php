<?php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/ulid.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);


/**
 * Google OAuth 開始
 */
if ($path === '/auth/google') {

  $serviceKey = $_GET['service']
    ?? $_POST['service']
    ?? 'demo';

  $serviceKey = trim((string) $serviceKey);

  if ($serviceKey === '') {
    http_response_code(400);
    exit('サービス情報がありません');
  }

  if (!isValidServiceKey($serviceKey)) {
    http_response_code(400);
    exit('無効なサービスです');
  }

  $state = bin2hex(random_bytes(16));

  $_SESSION['google_oauth_state'] = $state;
  $_SESSION['google_oauth_service_key'] = $serviceKey;

  $params = [
    'client_id' => getenv('GOOGLE_CLIENT_ID'),
    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI'),
    'response_type' => 'code',
    'scope' => 'openid',
    'state' => $state,
  ];

  $url = 'https://accounts.google.com/o/oauth2/v2/auth?' .
    http_build_query($params);

  header('Location: ' . $url);
  exit;
}


/**
 * ユーザーのサービス参加を保証する
 */
function ensureUserServiceRole(PDO $pdo, $userId, $serviceKey) {
  $stmt = $pdo->prepare('
    INSERT INTO user_service_roles (
      user_id,
      service_key,
      role,
      status
    ) VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      status = VALUES(status),
      updated_at = CURRENT_TIMESTAMP
  ');

  $stmt->execute([
    $userId,
    $serviceKey,
    'member',
    'active',
  ]);
}


/**
 * Google OAuth callback
 */
if ($path === '/auth/google/callback') {

  $code = $_GET['code'] ?? '';
  $state = $_GET['state'] ?? '';

  $serviceKey = $_SESSION['google_oauth_service_key'] ?? '';

  if (!$code) {
    exit('Google OAuth error: code がありません');
  }

  if (
    !$state ||
    !hash_equals(
      $_SESSION['google_oauth_state'] ?? '',
      $state
    )
  ) {
    exit('Google OAuth error: state が一致しません');
  }

  if (!$serviceKey) {
    http_response_code(400);
    exit('サービス情報がありません');
  }

  if (!isValidServiceKey($serviceKey)) {
    http_response_code(400);
    exit('無効なサービスです');
  }

  // Googleへ認証コードを送ってアクセストークンを取得
  $ch = curl_init('https://oauth2.googleapis.com/token');

  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
      'code' => $code,
      'client_id' => getenv('GOOGLE_CLIENT_ID'),
      'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
      'redirect_uri' => getenv('GOOGLE_REDIRECT_URI'),
      'grant_type' => 'authorization_code',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/x-www-form-urlencoded',
    ],
  ]);

  $response = curl_exec($ch);

  if ($response === false) {
    exit('Google OAuth error: ' . curl_error($ch));
  }

  curl_close($ch);

  $token = json_decode($response, true);

  if (isset($token['error'])) {
    exit('Google OAuth error: ' . $token['error']);
  }

  $idToken = $token['id_token'] ?? '';

  if (!$idToken) {
    exit('Google OAuth error: id_token がありません');
  }

  $parts = explode('.', $idToken);

  if (count($parts) !== 3) {
    exit('Google OAuth error: id_token の形式が不正です');
  }

  $payload = json_decode(
    base64_decode(strtr($parts[1], '-_', '+/')),
    true
  );

  if (!$payload) {
    exit('Google OAuth error: id_token の解析に失敗しました');
  }

  $googleUserId = $payload['sub'] ?? '';

  if (!$googleUserId) {
    exit('Google OAuth error: sub がありません');
  }

  // DB接続
  $pdo = createDatabaseConnection();

  // Googleアカウントを検索
  $stmt = $pdo->prepare('
    SELECT user_id
    FROM user_auth
    WHERE provider = ?
    AND provider_user_id = ?
  ');

  $stmt->execute([
    'google',
    $googleUserId,
  ]);

  $userAuth = $stmt->fetch();

  if ($userAuth) {

    // 既存ユーザー
    $userId = $userAuth['user_id'];

    ensureUserServiceRole(
      $pdo,
      $userId,
      $serviceKey
    );

    $_SESSION['services'][$serviceKey]['user_id'] = $userId;

    header('Location: /me?service=' . urlencode($serviceKey));
    exit;
  }

  // 新規ユーザー
  $pdo->beginTransaction();

  try {

    // usersを作成
    $publicId = generateUlid();

    $stmt = $pdo->prepare('
      INSERT INTO users (
        public_id,
        status
      ) VALUES (?, ?)
    ');

    $stmt->execute([
      $publicId,
      'active',
    ]);

    $userId = $pdo->lastInsertId();

    // user_authを作成
    $stmt = $pdo->prepare('
      INSERT INTO user_auth (
        user_id,
        provider,
        provider_user_id
      ) VALUES (?, ?, ?)
    ');

    $stmt->execute([
      $userId,
      'google',
      $googleUserId,
    ]);

    // サービスへの参加を保証
    ensureUserServiceRole(
      $pdo,
      $userId,
      $serviceKey
    );

    $pdo->commit();

    $_SESSION['services'][$serviceKey]['user_id'] = $userId;

    header('Location: /me?service=' . urlencode($serviceKey));
  } catch (Throwable $e) {

    $pdo->rollBack();

    throw $e;
  }

  exit;
}


/**
 * ログインユーザー取得
 */
if ($path === '/me') {

  $serviceKey = $_GET['service']
    ?? $_POST['service']
    ?? 'demo';

  $serviceKey = trim((string) $serviceKey);

  if ($serviceKey === '') {
    http_response_code(400);
    exit('サービス情報がありません');
  }

  if (!isValidServiceKey($serviceKey)) {
    http_response_code(400);
    exit('無効なサービスです');
  }

  $userId = $_SESSION['services'][$serviceKey]['user_id'] ?? null;

  if (!$userId) {
    http_response_code(401);
    echo 'ログインしていません';
    exit;
  }

  $pdo = createDatabaseConnection();

  // ユーザーが存在し、かつ対象サービスでactiveか確認
  $stmt = $pdo->prepare('
    SELECT
      u.public_id,
      u.status,
      u.created_at
    FROM users AS u

    INNER JOIN user_service_roles AS usr
      ON usr.user_id = u.id

    WHERE u.id = ?
    AND usr.service_key = ?
    AND usr.role = ?
    AND usr.status = ?
  ');

  $stmt->execute([
    $userId,
    $serviceKey,
    'member',
    'active',
  ]);

  $user = $stmt->fetch();

  if (!$user) {

    unset(
      $_SESSION['services'][$serviceKey]
    );

    http_response_code(401);
    echo 'ユーザーが存在しないか、サービスを利用できません';
    exit;
  }

  if ($user['status'] !== 'active') {

    unset(
      $_SESSION['services'][$serviceKey]
    );

    http_response_code(401);
    echo 'ユーザーが無効になっています';
    exit;
  }

  header('Content-Type: application/json; charset=UTF-8');

  echo json_encode([
    'user' => $user,
  ], JSON_UNESCAPED_UNICODE);

  exit;
}


/**
 * ログアウト
 */
if ($path === '/logout') {

  $serviceKey = $_GET['service']
    ?? $_POST['service']
    ?? 'demo';

  $serviceKey = trim((string) $serviceKey);

  unset(
    $_SESSION['services'][$serviceKey]
  );

  header('Content-Type: application/json; charset=UTF-8');

  echo json_encode([
    'message' => 'ログアウトしました',
    'service' => $serviceKey,
  ], JSON_UNESCAPED_UNICODE);

  exit;
}


echo 'API OK<br>';
echo 'MySQL OK<br>';