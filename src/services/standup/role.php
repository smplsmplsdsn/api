<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/config.php';

try {
  $pdo = createDatabaseConnection();

  $user_id = $_SESSION['services']['standup']['user_id'] ?? null;

  if (!$user_id) {
    http_response_code(401);

    echo json_encode([
      'success' => false,
      'error' => 'Not logged in',
    ], JSON_UNESCAPED_UNICODE);

    exit;
  }

  $stmt = $pdo->prepare('
    SELECT id
    FROM users
    WHERE id = ?
      AND status = ?
  ');

  $stmt->execute([
    $user_id,
    'active',
  ]);

  $user = $stmt->fetch();

  if (!$user) {
    http_response_code(401);

    echo json_encode([
      'success' => false,
      'error' => 'Invalid user',
    ], JSON_UNESCAPED_UNICODE);

    exit;
  }

  $input = json_decode(
    file_get_contents('php://input'),
    true
  );

  if (!is_array($input)) {
    http_response_code(400);

    echo json_encode([
      'success' => false,
      'error' => 'Invalid request body',
    ], JSON_UNESCAPED_UNICODE);

    exit;
  }

  $role = $input['role'] ?? '';
  $status = $input['status'] ?? '';

  if (!in_array($role, ['comedian', 'venue_manager'], true)) {
    http_response_code(400);

    echo json_encode([
      'success' => false,
      'error' => 'Invalid role',
    ], JSON_UNESCAPED_UNICODE);

    exit;
  }

  if (!in_array($status, ['active', 'inactive'], true)) {
    http_response_code(400);

    echo json_encode([
      'success' => false,
      'error' => 'Invalid status',
    ], JSON_UNESCAPED_UNICODE);

    exit;
  }

  $pdo->beginTransaction();

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
    $user_id,
    'standup',
    $role,
    $status,
  ]);

  if ($role === 'comedian' && $status === 'active') {
    $stmt = $pdo->prepare('
      SELECT id
      FROM standup_comedians
      WHERE user_id = ?
    ');

    $stmt->execute([
      $user_id,
    ]);

    $comedian = $stmt->fetch();

    if (!$comedian) {
      $stmt = $pdo->prepare('
        INSERT INTO standup_comedians (
          user_id,
          status
        ) VALUES (?, ?)
      ');

      $stmt->execute([
        $user_id,
        'inactive',
      ]);
    }
  }

  $pdo->commit();

  echo json_encode([
    'success' => true,
    'role' => $role,
    'status' => $status,
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  http_response_code(500);

  echo json_encode([
    'success' => false,
    'error' => 'Internal server error',
  ], JSON_UNESCAPED_UNICODE);
}