<?php

// 設定値を読み込む
$env = [];

if (file_exists(__DIR__ . "/../../.env")) {
  $parsedEnv = parse_ini_file(__DIR__ . "/../../.env");
  $env = $parsedEnv;
}


/**
 * リダイレクト先URLを取得する
 */
function getRedirectUrl($serviceKey) {
  global $env;
  global $serviceRedirectProduction;
  global $serviceRedirectLocal;

  if (($env['APP_ENV'] ?? '') === 'production') {
    $redirectUrl = $serviceRedirectProduction[$serviceKey] ?? null;
  } else {
    $redirectUrl = $serviceRedirectLocal[$serviceKey] ?? null;
  }

  return $redirectUrl;
}

/**
 * 有効なサービスか確認する
 */
function isValidServiceKey($serviceKey) {

  global $allowedServices;

  return in_array(
    $serviceKey,
    $allowedServices,
    true
  );
}

/**
 * .env または環境変数から値を取得する
 */
function getConfigValue($key, $env = [], $service_key = null) {

  if ($service_key !== null && $service_key !== '') {
    $full_key = strtoupper($service_key) . '_' . $key;
  } else {
    $full_key = $key;
  }

  if (isset($env[$full_key])) {
    return $env[$full_key];
  }

  $value = getenv($full_key);

  if ($value !== false) {
    return $value;
  }

  return null;
}

/**
 * DB接続を作成する
 */
function createDatabaseConnection() {

  global $env;

  $database = getConfigValue('MYSQL_DATABASE', $env);
  $user = getConfigValue('MYSQL_USER', $env);
  $password = getConfigValue('MYSQL_PASSWORD', $env);
  $host = getConfigValue('MYSQL_HOST', $env);

  if (!$host) {
    $host = 'mysql';
  }

  if (!$database || !$user || !$password) {
    throw new RuntimeException(
      'Database configuration is not set.'
    );
  }

  return new PDO(
    "mysql:host={$host};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
}

