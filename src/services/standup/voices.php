<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/functions/permissions.php';

function updateError(string $message, int $status = 400) {
  http_response_code($status);

  echo json_encode([
    'success' => false,
    'error' => $message
  ], JSON_UNESCAPED_UNICODE);

  exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['GET', 'POST'], true)) {
  header('Allow: GET, POST');
  updateError('Method not allowed', 405);
}

try {
  $pdo = createDatabaseConnection();

  /*
   * GET：投稿取得
   */
  if ($method === 'GET') {

    $comedian_id = $_GET['comedian_id'] ?? null;

    // コメディアンが有効か確認
    if (!isActiveComedianById($pdo, $comedian_id)) {
      updateError('Invalid comedian ID', 400);
    }

    // カーソルの取得
    // after_id: 指定した投稿IDより新しい投稿を取得
    // before_id: 指定した投稿IDより古い投稿を取得
    // どちらも未指定の場合：最新の投稿から取得
    $after_id = null;
    $before_id = null;

    if (array_key_exists('after_id', $_GET)) {
      $after_id = filter_var(
        $_GET['after_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
      );

      if ($after_id === false) {
        updateError('Invalid after ID', 400);
      }
    }

    if (array_key_exists('before_id', $_GET)) {
      $before_id = filter_var(
        $_GET['before_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
      );

      if ($before_id === false) {
        updateError('Invalid before ID', 400);
      }
    }

    // カーソルの同時指定を禁止
    if ($after_id !== null && $before_id !== null) {
      updateError('Invalid cursor parameters', 400);
    }

    $limit = 10;
    $has_more = false;

    if ($after_id !== null) {

      // 更新分の取得（指定した投稿IDより新しい投稿をすべて取得する）
      $stmt = $pdo->prepare('
        SELECT
          id,
          comedian_id,
          text,
          created_at
        FROM standup_comedian_voices
        WHERE comedian_id = ?
          AND id > ?
        ORDER BY id DESC
      ');

      $stmt->execute([
        $comedian_id,
        $after_id
      ]);

      $voices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($before_id !== null) {

      // 過去の投稿取得
      $stmt = $pdo->prepare('
        SELECT
          id,
          comedian_id,
          text,
          created_at
        FROM standup_comedian_voices
        WHERE comedian_id = ?
          AND id < ?
        ORDER BY id DESC
        LIMIT ' . ($limit + 1)
      );

      $stmt->execute([
        $comedian_id,
        $before_id
      ]);

      $voices = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $has_more = count($voices) > $limit;
      $voices = array_slice($voices, 0, $limit);

    } else {

      // 初回取得
      $stmt = $pdo->prepare('
        SELECT
          id,
          comedian_id,
          text,
          created_at
        FROM standup_comedian_voices
        WHERE comedian_id = ?
        ORDER BY id DESC
        LIMIT ' . ($limit + 1)
      );

      $stmt->execute([$comedian_id]);

      $voices = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $has_more = count($voices) > $limit;

      $voices = array_slice($voices, 0, $limit);
    }

    // IDの型を統一
    foreach ($voices as &$post) {
      $post['id'] = (int) $post['id'];
      $post['comedian_id'] = (int) $post['comedian_id'];
    }

    // foreach で参照に使った変数の解除
    unset($post);

    echo json_encode([
      'success' => true,
      'voices' => $voices,
      'has_more' => $has_more
    ], JSON_UNESCAPED_UNICODE);

    exit;
  }

  /*
   * POST：投稿登録
   */

  // ログイン状態の確認
  $user_id = $_SESSION['services']['standup']['user_id'] ?? null;

  if (!$user_id) {
    updateError('Not logged in', 401);
  }

  // ユーザーの有効状態を確認
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
    updateError('Invalid user', 401);
  }

  // comedian権限の確認とID取得
  $comedian_id = getActiveComedianId($pdo, $user_id);

  if ($comedian_id === null) {
    updateError('Comedian permission required', 403);
  }

  // リクエストの取得
  $input = json_decode(
    file_get_contents('php://input'),
    true
  );

  if (!is_array($input)) {
    updateError('Invalid request body', 400);
  }


  // リクエスト本文の取得
  $text = $input['text'] ?? null;

  if (!is_string($text) || trim($text) === '') {
    updateError('Text is required', 400);
  }

  $text = trim($text);

  // 文字数の確認（必要に応じて上限を設定）
  if (mb_strlen($text) > 300) {
    updateError('Text is too long', 400);
  }

  // つぶやきの登録
  $stmt = $pdo->prepare('
    INSERT INTO standup_comedian_voices (
      comedian_id,
      text
    ) VALUES (?, ?)
  ');

  $stmt->execute([
    $comedian_id,
    $text,
  ]);

  echo json_encode([
    'success' => true,
    'voice_id' => (int) $pdo->lastInsertId(),
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  error_log($e->getMessage());

  updateError('Internal server error', 500);
}