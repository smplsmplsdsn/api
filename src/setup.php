<?php
$target_dir = [
  '000_common',
  '001_standup',
  '101_stations'
];

$envFile = __DIR__ . '/../.env';
$sqlDir = __DIR__ . '/../app/database';

header('Content-Type: text/plain; charset=UTF-8');

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
 * 指定ディレクトリ内のSQLファイルを実行する
 */
function createTable($dir_type, $sqlDir, $env) {
  $targetDir = $sqlDir . '/' . $dir_type;

  if (!is_dir($targetDir)) {
    throw new RuntimeException(
      "SQL directory not found: {$targetDir}"
    );
  }

  /*
   * DB接続先を切り替える
   */
  switch ($dir_type) {
    case '101_stations':
      $host = getConfigValue('STATIONS_DB_HOST', $env);
      $database = getConfigValue('STATIONS_DB_NAME', $env);
      $user = getConfigValue('STATIONS_DB_USER', $env);
      $password = getConfigValue('STATIONS_DB_PASSWORD', $env);
      break;

    default:
      $host = getConfigValue('MYSQL_HOST', $env);
      $database = getConfigValue('MYSQL_DATABASE', $env);
      $user = getConfigValue('MYSQL_USER', $env);
      $password = getConfigValue('MYSQL_PASSWORD', $env);
      break;
  }

  if (!$host || !$database || !$user || $password === null) {
    throw new RuntimeException(
      "Database configuration is not set: {$dir_type}"
    );
  }

  /*
   * SQLファイルを取得してファイル名順に並べる
   */
  $sqlFiles = [];

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
      $targetDir,
      FilesystemIterator::SKIP_DOTS
    )
  );

  foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'sql') {
      $sqlFiles[] = $file->getPathname();
    }
  }

  sort($sqlFiles, SORT_STRING);

  if (empty($sqlFiles)) {
    throw new RuntimeException(
      "No SQL files found: {$targetDir}"
    );
  }

  // DB接続
  $pdo = new PDO(
    "mysql:host={$host};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );

  echo "Database setup started: {$dir_type}\n";

  foreach ($sqlFiles as $sqlFile) {
    $fileName = basename($sqlFile);
    $sql = file_get_contents($sqlFile);

    if ($sql === false) {
      throw new RuntimeException(
        "Failed to read SQL file: {$fileName}"
      );
    }

    $pdo->exec($sql);

    echo "OK: {$fileName}\n";
  }

  echo "Database setup completed: {$dir_type}\n\n";
}

try {

  // .env の読み込み
  $env = [];

  if (file_exists($envFile)) {
    $parsedEnv = parse_ini_file($envFile);

    if ($parsedEnv === false) {
      throw new RuntimeException(
        'Failed to read .env file.'
      );
    }

    $env = $parsedEnv;
  }

  // DBセットアップ
  foreach ($target_dir as $dir) {
    createTable($dir, $sqlDir, $env);
  }

  echo "All database setups completed.\n";

  $self = __FILE__;

  if (!unlink($self)) {
    throw new RuntimeException(
      'Database setup completed, but the setup file could not be deleted.'
    );
  }

  echo "Setup file deleted.\n";
} catch (Throwable $e) {
  http_response_code(500);

  echo "ERROR: {$e->getMessage()}\n";
}
