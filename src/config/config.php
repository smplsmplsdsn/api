<?php
$allowedServices = [
  'demo',
];

$env = [];
$envFile = __DIR__ . '/../../.env';


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
function getConfigValue($key, $env) {

  if (isset($env[$key])) {
    return $env[$key];
  }

  $value = getenv($key);

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

