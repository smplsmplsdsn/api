<?php
$env = [];
$envFile = __DIR__ . "/../../.env";

ini_set('display_errors', 1);
error_reporting(E_ALL);

/**
 * リダイレクト先URLを取得する
 */
function getRedirectUrl($serviceKey) {
  global $serviceRedirectProduction;
  global $serviceRedirectLocal;

  $host = $_SERVER['HTTP_HOST'] ?? '';

  $is_production = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') &&
    stripos($host, 'localhost') === false
  );

  if ($is_production) {
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
 * 設定値を読み込む
 */
function loadConfig() {

  global $envFile;

  $env = [];

  /*
   * .env が存在する場合は読み込む。
   * Docker環境などで存在しない場合は、
   * Docker Composeから渡された環境変数を使用する。
   */
  if (file_exists($envFile)) {
    $parsedEnv = parse_ini_file($envFile);

    if ($parsedEnv === false) {
      throw new RuntimeException(
        'Failed to read .env file.'
      );
    }

    $env = $parsedEnv;
  }

  return $env;
}


/**
 * DB接続を作成する
 */
function createDatabaseConnection() {

  $env = loadConfig();

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

