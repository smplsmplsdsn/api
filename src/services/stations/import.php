
<?php
/*
 + データベース更新 インクルード用ファイル
 + update.php から読みこまえれている
 */

/**
 * Find exactly one CSV file matching the specified pattern.
 */
function findStationsCsvFile(
  string $csvDir,
  string $pattern,
  string $label,
  array &$errors
): ?string {
  $files = glob("{$csvDir}/{$pattern}");

  if ($files === false) {
    $errors[] = "{$label}: ファイル一覧を取得できません";
    return null;
  }

  $files = array_values(array_filter(
    $files,
    static fn($path) => is_file($path)
  ));

  if (count($files) === 0) {
    $errors[] = "{$label}: 対象ファイルが見つかりません";
    return null;
  }

  if (count($files) > 1) {
    $errors[] = "{$label}: 対象ファイルが複数あります";
    return null;
  }

  if (!is_readable($files[0])) {
    $errors[] = "{$label}: ファイルを読み込めません";
    return null;
  }

  return $files[0];
}

/**
 * Read a CSV file and verify its header and row width.
 */
function readStationsCsv(
  string $path,
  array $expectedHeaders,
  string $label,
  array &$errors
): array {
  if (!is_file($path) || !is_readable($path)) {
    $errors[] = "{$label}: ファイルが存在しないか、読み込めません";
    return [];
  }

  $handle = fopen($path, 'r');

  if ($handle === false) {
    $errors[] = "{$label}: ファイルを開けません";
    return [];
  }

  try {
    $headers = fgetcsv($handle, 0, ',', '"', '\\');

    if ($headers === false) {
      $errors[] = "{$label}: ヘッダーがありません";
      return [];
    }

    $headers[0] = preg_replace(
      '/^\xEF\xBB\xBF/',
      '',
      (string) $headers[0]
    );

    $headers = array_map('trim', $headers);

    if ($headers !== $expectedHeaders) {
      $errors[] = "{$label}: ヘッダーが想定と一致しません";
      return [];
    }

    $rows = [];
    $lineNumber = 1;

    while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
      $lineNumber++;

      if ($values === [null] || $values === []) {
        continue;
      }

      if (count($values) !== count($expectedHeaders)) {
        $errors[] = "{$label}: {$lineNumber}行目のカラム数が不正です";
        continue;
      }

      $values = array_map(
        static fn($value) => trim((string) $value),
        $values
      );

      $row = array_combine($expectedHeaders, $values);

      if ($row === false) {
        $errors[] = "{$label}: {$lineNumber}行目を解析できません";
        continue;
      }

      $rows[] = $row;
    }

    return $rows;
  } finally {
    fclose($handle);
  }
}

/**
 * Validate required fields, value formats and unique keys.
 *
 * Cross-file reference checks are intentionally omitted.
 */
function validateStationsCsv(
  array $stations,
  array $lines,
  array $joins,
  array &$errors
): void {
  $definitions = [
    'station' => [
      'rows' => $stations,
      'key' => 'station_cd',
      'required' => ['station_cd', 'station_name', 'line_cd'],
      'integers' => [
        'station_cd', 'station_g_cd', 'line_cd', 'pref_cd', 'e_status', 'e_sort'
      ],
      'decimals' => ['lon', 'lat'],
      'dates' => ['open_ymd', 'close_ymd']
    ],
    'line' => [
      'rows' => $lines,
      'key' => 'line_cd',
      'required' => ['line_cd', 'line_name'],
      'integers' => [
        'line_cd', 'company_cd', 'line_type', 'zoom', 'e_status', 'e_sort'
      ],
      'decimals' => ['lon', 'lat'],
      'dates' => []
    ],
    'join' => [
      'rows' => $joins,
      'key' => null,
      'required' => ['line_cd', 'station_cd1', 'station_cd2'],
      'integers' => ['line_cd', 'station_cd1', 'station_cd2'],
      'decimals' => [],
      'dates' => []
    ]
  ];

  foreach ($definitions as $name => $definition) {
    if (count($definition['rows']) === 0) {
      $errors[] = "{$name}.csv: データ行がありません。空のCSVによる誤更新を防ぐため中止します";
      continue;
    }

    $seen = [];

    foreach ($definition['rows'] as $index => $row) {
      $rowNumber = $index + 2;

      foreach ($definition['required'] as $column) {
        if (($row[$column] ?? '') === '') {
          $errors[] = "{$name}.csv: {$rowNumber}行目の{$column}が空です";
        }
      }

      foreach ($definition['integers'] as $column) {
        $value = $row[$column] ?? '';

        if ($value !== '' && !preg_match('/^\d+$/D', $value)) {
          $errors[] = "{$name}.csv: {$rowNumber}行目の{$column}が整数コードではありません";
        }
      }

      foreach ($definition['decimals'] as $column) {
        $value = $row[$column] ?? '';

        if ($value !== '' && !is_numeric($value)) {
          $errors[] = "{$name}.csv: {$rowNumber}行目の{$column}が数値ではありません";
        }
      }

      foreach ($definition['dates'] as $column) {
        $value = $row[$column] ?? '';

        // Empty dates and 0000-00-00 represent unset dates.
        if ($value === '' || $value === '0000-00-00') {
          continue;
        }

        if (
          !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)
          || !checkdate(
            (int) substr($value, 5, 2),
            (int) substr($value, 8, 2),
            (int) substr($value, 0, 4)
          )
        ) {
          $errors[] = "{$name}.csv: {$rowNumber}行目の{$column}が日付として不正です";
        }
      }

      // Check duplicate primary keys in station and line CSVs.
      if ($definition['key'] !== null) {
        $key = $row[$definition['key']] ?? '';

        if ($key !== '') {
          if (isset($seen[$key])) {
            $errors[] = "{$name}.csv: {$rowNumber}行目の主キーが重複しています ({$key})";
          }

          $seen[$key] = true;
        }
      }
    }
  }

  // Check duplicate connection keys within join CSV.
  $seenJoins = [];

  foreach ($joins as $index => $join) {
    $rowNumber = $index + 2;

    $key = implode('|', [
      $join['line_cd'],
      $join['station_cd1'],
      $join['station_cd2']
    ]);

    if (isset($seenJoins[$key])) {
      $errors[] = "join.csv: {$rowNumber}行目の接続キーが重複しています";
    }

    $seenJoins[$key] = true;
  }
}

/**
 * Insert or update rows without deleting existing station/line records.
 */
function upsertStations(
  PDO $pdo,
  string $table,
  array $columns,
  string $primaryKey,
  array $rows
): void {
  $columnSql = implode(
    ', ',
    array_map(static fn($column) => "`{$column}`", $columns)
  );

  $placeholders = implode(
    ', ',
    array_fill(0, count($columns), '?')
  );

  $updates = array_values(array_filter(
    $columns,
    static fn($column) => $column !== $primaryKey
  ));

  $updateSql = implode(
    ', ',
    array_map(
      static fn($column) => "`{$column}` = VALUES(`{$column}`)",
      $updates
    )
  );

  $sql = "INSERT INTO `{$table}` ({$columnSql}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$updateSql}";

  $stmt = $pdo->prepare($sql);

  foreach ($rows as $row) {
    $stmt->execute(array_map(
      static fn($column) => $row[$column],
      $columns
    ));
  }
}

/**
 * Import station, line and connection CSV data.
 */
function runStationsImport(): array {
  $csvDir = __DIR__ . '/csv';
  $errors = [];

  $stationColumns = [
    'station_cd', 'station_g_cd', 'station_name', 'station_name_k', 'station_name_r',
    'line_cd', 'pref_cd', 'post', 'address', 'lon', 'lat', 'open_ymd', 'close_ymd',
    'e_status', 'e_sort'
  ];

  $lineColumns = [
    'line_cd', 'company_cd', 'line_name', 'line_name_k', 'line_name_h',
    'line_color_c', 'line_color_t', 'line_type', 'lon', 'lat', 'zoom', 'e_status', 'e_sort'
  ];

  $joinColumns = ['line_cd', 'station_cd1', 'station_cd2'];

  // CSVファイルを各1件ずつ取得する
  $stationPath = findStationsCsvFile(
    $csvDir,
    'station*.csv',
    '駅CSV',
    $errors
  );

  $linePath = findStationsCsvFile(
    $csvDir,
    'line*.csv',
    '路線CSV',
    $errors
  );

  $joinPath = findStationsCsvFile(
    $csvDir,
    'join*.csv',
    '接続CSV',
    $errors
  );

  if ($errors !== []) {
    return [
      'success' => false,
      'stage' => 'validation',
      'errors' => array_slice($errors, 0, 50),
      'error_count' => count($errors)
    ];
  }

  // CSVを読み込む
  $stations = readStationsCsv(
    $stationPath,
    $stationColumns,
    basename($stationPath),
    $errors
  );

  $lines = readStationsCsv(
    $linePath,
    $lineColumns,
    basename($linePath),
    $errors
  );

  $joins = readStationsCsv(
    $joinPath,
    $joinColumns,
    basename($joinPath),
    $errors
  );

  // CSVの形式・必須項目・値・重複を検証する
  // ファイル間の参照整合性チェックは行わない
  validateStationsCsv($stations, $lines, $joins, $errors);

  if ($errors !== []) {
    return [
      'success' => false,
      'stage' => 'validation',
      'errors' => array_slice($errors, 0, 50),
      'error_count' => count($errors)
    ];
  }

  // ルーティングで読み込まれた設定値を使用する
  global $env;

  $host = getConfigValue('STATIONS_DB_HOST', $env);
  $database = getConfigValue('STATIONS_DB_NAME', $env);
  $user = getConfigValue('STATIONS_DB_USER', $env);
  $password = getConfigValue('STATIONS_DB_PASSWORD', $env);

  if (!$host || !$database || !$user || $password === null) {
    throw new RuntimeException('駅DBの接続設定が不足しています');
  }

  $pdo = new PDO(
    "mysql:host={$host};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false
    ]
  );

  // 3テーブルをトランザクション内で全件入れ替える
  $pdo->beginTransaction();

  try {
    // 外部キー制約を考慮して接続データから削除する
    $pdo->exec('DELETE FROM m_station_join');
    $pdo->exec('DELETE FROM m_station');
    $pdo->exec('DELETE FROM m_line');

    // 駅データを全件登録する
    $stationColumnSql = implode(
      ', ',
      array_map(
        static fn($column) => "`{$column}`",
        $stationColumns
      )
    );

    $stationPlaceholders = implode(
      ', ',
      array_fill(0, count($stationColumns), '?')
    );

    $stmt = $pdo->prepare(
      "INSERT INTO m_station ({$stationColumnSql}) VALUES ({$stationPlaceholders})"
    );

    foreach ($stations as $station) {
      $stmt->execute(array_map(
        static fn($column) => $station[$column],
        $stationColumns
      ));
    }

    // 路線データを全件登録する
    $lineColumnSql = implode(
      ', ',
      array_map(
        static fn($column) => "`{$column}`",
        $lineColumns
      )
    );

    $linePlaceholders = implode(
      ', ',
      array_fill(0, count($lineColumns), '?')
    );

    $stmt = $pdo->prepare(
      "INSERT INTO m_line ({$lineColumnSql}) VALUES ({$linePlaceholders})"
    );

    foreach ($lines as $line) {
      $stmt->execute(array_map(
        static fn($column) => $line[$column],
        $lineColumns
      ));
    }

    // 駅間接続データを全件登録する
    $stmt = $pdo->prepare(
      'INSERT INTO m_station_join (line_cd, station_cd1, station_cd2) VALUES (?, ?, ?)'
    );

    foreach ($joins as $join) {
      $stmt->execute([
        $join['line_cd'],
        $join['station_cd1'],
        $join['station_cd2']
      ]);
    }

    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }

    throw $e;
  }

  return [
    'success' => true,
    'counts' => [
      'stations' => count($stations),
      'lines' => count($lines),
      'joins' => count($joins)
    ]
  ];
}

