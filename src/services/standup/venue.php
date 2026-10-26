<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/functions/permissions.php';

// エラー返却
function updateError(string $message, int $status = 400): void {
  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

// IDの形式を確認
function isValidId($id): bool {
  return is_string($id)
    && ctype_digit($id)
    && (int)$id > 0;
}

// 会場情報のステータスを取得
function getVenueStatus(PDO $pdo, int $venue_id): ?string {
  $stmt = $pdo->prepare('
    SELECT status
    FROM standup_venues
    WHERE id = :id
    LIMIT 1
  ');

  $stmt->execute([':id' => $venue_id]);

  $status = $stmt->fetchColumn();

  return $status === false ? null : $status;
}

// 会場情報を取得
function getVenue(PDO $pdo, int $venue_id): ?array {
  $stmt = $pdo->prepare('
    SELECT
      id,
      name,
      address,
      latitude,
      longitude,
      stations,
      instagram,
      tiktok,
      youtube,
      x,
      message,
      message_for_comedian,
      status
    FROM standup_venues
    WHERE id = :id
    LIMIT 1
  ');

  $stmt->execute([':id' => $venue_id]);

  $venue = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$venue) {
    return null;
  }

  $venue['id'] = (string)$venue['id'];

  $venue['latlng'] = [
    'lat' => $venue['latitude'] !== null
      ? (float)$venue['latitude']
      : null,
    'lng' => $venue['longitude'] !== null
      ? (float)$venue['longitude']
      : null
  ];

  unset($venue['latitude'], $venue['longitude']);

  $stations = json_decode($venue['stations'], true);
  $venue['stations'] = is_array($stations) ? $stations : [];

  $venue['socialmedia'] = [
    'instagram' => $venue['instagram'],
    'tiktok' => $venue['tiktok'],
    'youtube' => $venue['youtube'],
    'x' => $venue['x']
  ];

  unset(
    $venue['instagram'],
    $venue['tiktok'],
    $venue['youtube'],
    $venue['x']
  );

  // 担当管理者の公開IDを取得
  $stmt = $pdo->prepare('
    SELECT u.public_id
    FROM standup_venue_managers vm
    INNER JOIN users u
      ON u.id = vm.user_id
    WHERE vm.venue_id = :venue_id
      AND vm.status = "active"
  ');

  $stmt->execute([':venue_id' => $venue_id]);

  $venue['managers'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

  return $venue;
}

// 指定会場の担当管理者か確認
function getVenueManagerAccess(
  PDO $pdo,
  int $venue_id,
  int $user_id
): bool {
  $stmt = $pdo->prepare('
    SELECT 1
    FROM standup_venue_managers
    WHERE venue_id = :venue_id
      AND user_id = :user_id
      AND status = "active"
    LIMIT 1
  ');

  $stmt->execute([
    ':venue_id' => $venue_id,
    ':user_id' => $user_id
  ]);

  return (bool)$stmt->fetchColumn();
}

// 会場に有効な担当管理者がいるか確認
function hasActiveVenueManagers(
  PDO $pdo,
  int $venue_id
): bool {
  $stmt = $pdo->prepare('
    SELECT 1
    FROM standup_venue_managers
    WHERE venue_id = :venue_id
      AND status = "active"
    LIMIT 1
  ');

  $stmt->execute([':venue_id' => $venue_id]);

  return (bool)$stmt->fetchColumn();
}

// ユーザーに有効なコメディアン情報があるか確認
function getActiveComedianForUser(
  PDO $pdo,
  int $user_id
): bool {
  return getActiveComedianId($pdo, $user_id) !== null;
}

try {
  $method = $_SERVER['REQUEST_METHOD'];

  // POSTのみ許可
  if ($method !== 'POST') {
    header('Allow: POST');
    updateError('Method not allowed', 405);
  }

  $pdo = createDatabaseConnection();

  // ログインユーザーを確認
  $user_id = $_SESSION['services']['standup']['user_id'] ?? null;

  if (!$user_id) {
    updateError('Unauthorized', 401);
  }

  // ユーザーが有効か確認
  $stmt = $pdo->prepare('
    SELECT id
    FROM users
    WHERE id = :id
      AND status = "active"
    LIMIT 1
  ');

  $stmt->execute([':id' => $user_id]);

  if (!$stmt->fetchColumn()) {
    updateError('Unauthorized', 401);
  }

  $user_id = (int)$user_id;

  // 有効なコメディアンロールを確認
  $is_comedian_role = hasActiveRole(
    $pdo,
    $user_id,
    'standup',
    'comedian'
  );

  // 有効なコメディアン情報があるか確認
  $is_active_comedian = $is_comedian_role
    && getActiveComedianForUser($pdo, $user_id);

  // 有効な会場管理者ロールを確認
  $is_venue_manager = hasActiveRole(
    $pdo,
    $user_id,
    'standup',
    'venue_manager'
  );

  // コメディアンまたは会場管理者であること
  if (!$is_active_comedian && !$is_venue_manager) {
    updateError('Forbidden 1', 403);
  }


  /*
   * POST: 新規登録・更新
   */
  $id = $_POST['id'] ?? null;
  $is_new = $id === null || $id === '';

  if (!$is_new && !isValidId($id)) {
    updateError('Invalid venue id', 400);
  }

  $venue_id = $is_new ? null : (int)$id;

  // 会場名は必須
  $name = trim((string)($_POST['name'] ?? ''));

  if ($name === '') {
    updateError('店名・会場名は入力必須です。', 400);
  }

  if (mb_strlen($name) > 255) {
    updateError('Venue name is too long', 400);
  }

  // 編集対象の会場を確認
  if (!$is_new) {
    $venue_status = getVenueStatus($pdo, $venue_id);

    if ($venue_status !== 'active') {
      updateError('Venue not found', 404);
    }

    $has_managers = hasActiveVenueManagers($pdo, $venue_id);

    $can_manage = $is_venue_manager
      && getVenueManagerAccess($pdo, $venue_id, $user_id);

    // 有効な担当管理者がいる場合は担当管理者のみ編集可能
    if ($has_managers && !$can_manage) {
      updateError('Forbidden 2', 403);
    }

    // 担当管理者がいない場合はアクティブなコメディアンも編集可能
    if ($has_managers) {
      if (!$is_venue_manager || !$can_manage) {
        updateError('You do not have permission to edit this venue.', 403);
      }
    } else {
      if (!$is_active_comedian && !$is_venue_manager) {
        updateError('You do not have permission to edit this venue.', 403);
      }
    }

    // 担当管理者は全項目、それ以外のコメディアンは制限付き
    $can_save_all_fields = $can_manage;
  } else {
    // 新規登録時は会場管理者ロールがあれば全項目を保存可能
    $can_save_all_fields = $is_venue_manager;
  }

  // コメディアンが保存できる項目
  $allowed_fields = [
    'name',
    'latitude',
    'longitude',
    'stations',
    'instagram',
    'tiktok',
    'youtube',
    'x'
  ];

  // 会場管理者は全項目を保存可能
  if ($can_save_all_fields) {
    $allowed_fields = array_merge($allowed_fields, [
      'address',
      'message',
      'message_for_comedian'
    ]);
  }

  $values = [];

  foreach ($allowed_fields as $field) {
    if (!array_key_exists($field, $_POST)) {
      continue;
    }

    $value = $_POST[$field];

    if ($field === 'name') {
      $values[$field] = $name;
      continue;
    }

    // 緯度・経度
    if ($field === 'latitude' || $field === 'longitude') {
      if ($value === '') {
        $values[$field] = null;
        continue;
      }

      if (!is_numeric($value)) {
        updateError("Invalid {$field}", 400);
      }

      $number = (float)$value;

      if (
        ($field === 'latitude' && ($number < -90 || $number > 90))
        || ($field === 'longitude' && ($number < -180 || $number > 180))
      ) {
        updateError("Invalid {$field}", 400);
      }

      $values[$field] = $number;
      continue;
    }

    // 最寄駅情報のJSON
    if ($field === 'stations') {
      if (!is_string($value)) {
        updateError('Invalid stations JSON', 400);
      }

      $stations = json_decode($value, true);

      if (json_last_error() !== JSON_ERROR_NONE || !is_array($stations)) {
        updateError('Invalid stations JSON', 400);
      }

      $values[$field] = json_encode(
        $stations,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
      );

      continue;
    }

    // 文字列項目
    if (!is_string($value)) {
      updateError("Invalid {$field}", 400);
    }

    $max_lengths = [
      'address' => 500,
      'instagram' => 255,
      'tiktok' => 255,
      'youtube' => 255,
      'x' => 255
    ];

    if (
      isset($max_lengths[$field])
      && mb_strlen($value) > $max_lengths[$field]
    ) {
      updateError("{$field} is too long", 400);
    }

    $values[$field] = $value;
  }

  // 会場名は必ず保存
  $values['name'] = $name;

  // ---------------------------------
  // DB保存
  // ---------------------------------
  $pdo->beginTransaction();

  if ($is_new) {
    // 新規登録
    $columns = array_keys($values);

    $placeholders = array_map(
      fn($column) => ':' . $column,
      $columns
    );

    $sql = '
      INSERT INTO standup_venues (
        ' . implode(', ', $columns) . '
      ) VALUES (
        ' . implode(', ', $placeholders) . '
      )
    ';

    $stmt = $pdo->prepare($sql);

    $params = [];

    foreach ($values as $field => $value) {
      $params[':' . $field] = $value;
    }

    $stmt->execute($params);

    $venue_id = (int)$pdo->lastInsertId();

    // 会場管理者による新規登録時は登録者を担当管理者に設定
    if ($can_save_all_fields && $is_venue_manager) {
      $stmt = $pdo->prepare('
        INSERT INTO standup_venue_managers (
          venue_id,
          user_id,
          status
        ) VALUES (
          :venue_id,
          :user_id,
          "active"
        )
      ');

      $stmt->execute([
        ':venue_id' => $venue_id,
        ':user_id' => $user_id
      ]);
    }
  } else {
    // 更新
    $assignments = [];

    foreach (array_keys($values) as $field) {
      $assignments[] = "{$field} = :{$field}";
    }

    $sql = '
      UPDATE standup_venues
      SET ' . implode(', ', $assignments) . '
      WHERE id = :venue_id
    ';

    $stmt = $pdo->prepare($sql);

    $params = [];

    foreach ($values as $field => $value) {
      $params[':' . $field] = $value;
    }

    $params[':venue_id'] = $venue_id;

    $stmt->execute($params);
  }

  $pdo->commit();

  // 保存後の会場データを取得
  $venue = getVenue($pdo, $venue_id);

  // TODO $venueを取得できなかった場合のケア

  // 成功レスポンス
  echo json_encode([
    'success' => true,
    'id' => (string) $venue_id,
    'venue' => $venue
  ], JSON_UNESCAPED_UNICODE);

  exit;

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  error_log((string)$e);

  updateError('Internal server error', 500);
}
