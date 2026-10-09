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

  if (!$stmt->fetch()) {
    http_response_code(401);

    echo json_encode([
      'success' => false,
      'error' => 'Invalid user',
    ], JSON_UNESCAPED_UNICODE);

    exit;
  }

  $stmt = $pdo->prepare('
    SELECT id
    FROM user_service_roles
    WHERE user_id = ?
      AND service_key = ?
      AND role = ?
      AND status = ?
  ');

  $stmt->execute([
    $user_id,
    'standup',
    'comedian',
    'active',
  ]);

  if (!$stmt->fetch()) {
    http_response_code(403);

    echo json_encode([
      'success' => false,
      'error' => 'Comedian role is inactive',
    ], JSON_UNESCAPED_UNICODE);

    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $tiktok = trim($_POST['tiktok'] ?? '');
    $youtube = trim($_POST['youtube'] ?? '');
    $x = trim($_POST['x'] ?? '');

    $stmt = $pdo->prepare('
      SELECT
        id,
        thumbnail
      FROM standup_comedians
      WHERE user_id = ?
    ');

    $stmt->execute([
      $user_id,
    ]);

    $comedian = $stmt->fetch();

    if (!$comedian) {
      http_response_code(404);

      echo json_encode([
        'success' => false,
        'error' => 'Comedian profile not found',
      ], JSON_UNESCAPED_UNICODE);

      exit;
    }

    $status = $name !== '' && $comedian['thumbnail'] !== ''
      ? 'active'
      : 'inactive';

    $stmt = $pdo->prepare('
      UPDATE standup_comedians
      SET
        name = ?,
        instagram = ?,
        tiktok = ?,
        youtube = ?,
        x = ?,
        status = ?
      WHERE user_id = ?
    ');

    $stmt->execute([
      $name,
      $instagram,
      $tiktok,
      $youtube,
      $x,
      $status,
      $user_id,
    ]);

    echo json_encode([
      'success' => true,
      'comedian' => [
        'id' => (string) $comedian['id'],
        'name' => $name,
        'thumbnail' => $comedian['thumbnail'],
        'socialmedia' => [
          'instagram' => $instagram,
          'tiktok' => $tiktok,
          'youtube' => $youtube,
          'x' => $x,
        ],
        'status' => $status,
      ],
    ], JSON_UNESCAPED_UNICODE);

    exit;
  }

  $stmt = $pdo->prepare('
    SELECT
      id,
      name,
      thumbnail,
      instagram,
      tiktok,
      youtube,
      x,
      status
    FROM standup_comedians
    WHERE user_id = ?
  ');

  $stmt->execute([
    $user_id,
  ]);

  $comedian = $stmt->fetch();

  if (!$comedian) {
    http_response_code(404);

    echo json_encode([
      'success' => false,
      'error' => 'Comedian profile not found',
    ], JSON_UNESCAPED_UNICODE);

    exit;
  }

  echo json_encode([
    'success' => true,
    'comedian' => [
      'id' => (string) $comedian['id'],
      'name' => $comedian['name'],
      'thumbnail' => $comedian['thumbnail'],
      'socialmedia' => [
        'instagram' => $comedian['instagram'],
        'tiktok' => $comedian['tiktok'],
        'youtube' => $comedian['youtube'],
        'x' => $comedian['x'],
      ],
      'status' => $comedian['status'],
    ],
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);

  echo json_encode([
    'success' => false,
    'error' => 'Internal server error',
  ], JSON_UNESCAPED_UNICODE);
}