<?php

$envFile = __DIR__ . '/../.env';
$sqlDir = __DIR__ . '/../database';

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

try {
  /*
   * .env が存在する場合は読み込む。
   * Docker環境などで存在しない場合は、
   * Docker Composeから渡された環境変数を使用する。
   */
  $env = [];

  if (file_exists($envFile)) {
    $parsedEnv = parse_ini_file($envFile);

    if ($parsedEnv === false) {
      throw new RuntimeException('Failed to read .env file.');
    }

    $env = $parsedEnv;
  }

  $database = getConfigValue('MYSQL_DATABASE', $env);
  $user = getConfigValue('MYSQL_USER', $env);
  $password = getConfigValue('MYSQL_PASSWORD', $env);

  if (!$database || !$user || !$password) {
    throw new RuntimeException(
      'Database configuration is not set.'
    );
  }

  if (!is_dir($sqlDir)) {
    throw new RuntimeException(
      "SQL directory not found: {$sqlDir}"
    );
  }

  /*
   * databaseディレクトリ内のSQLファイルを再帰的に取得
   */
  $sqlFiles = [];

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
      $sqlDir,
      FilesystemIterator::SKIP_DOTS
    )
  );

  foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'sql') {
      $sqlFiles[] = $file->getPathname();
    }
  }

  // 実行順を統一
  sort($sqlFiles);

  if (empty($sqlFiles)) {
    exit('ERROR: No SQL files found.');
  }

  /*
   * ファイル名順に並べる
   */
  sort($sqlFiles, SORT_STRING);

  /*
   * DB接続
   *
   * Dockerでは mysql
   * 本番では環境に応じたMYSQL_HOSTを設定できるようにする。
   */
  $host = getConfigValue('MYSQL_HOST', $env);

  if (!$host) {
    $host = 'mysql';
  }

  $pdo = new PDO(
    "mysql:host={$host};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );

  echo "Database setup started.\n\n";

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

  echo "\nDatabase setup completed.\n";
} catch (Throwable $e) {
  http_response_code(500);

  echo "ERROR: {$e->getMessage()}\n";
}