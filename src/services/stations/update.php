<?php
/*
 + データベース更新用ファイル
 + ファイル内で import.php を読み込んでいる
 *
 * データ元 駅データ.jp https://ekidata.jp/dl/?p=1
 * 最新のCSVを差し替えたら、下記にアクセス（パスワードは env）
 *
 * http://localhost:8092/update?service=stations
 * https://api.standupspot.com/update?service=stations
 *
 * 更新後はログアウトすること
 */

declare(strict_types=1);

// ルーター側で session_start() 済みであること
if (session_status() !== PHP_SESSION_ACTIVE) {
  http_response_code(500);
  exit('セッションが開始されていません');
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

function h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// CSRFトークンを初期化
if (
  !isset($_SESSION['stations_admin_csrf'])
  || !is_string($_SESSION['stations_admin_csrf'])
) {
  $_SESSION['stations_admin_csrf'] = bin2hex(random_bytes(32));
}

$isAuthenticated = (
  ($_SESSION['stations_admin_authenticated'] ?? false) === true
);

$result = null;
$errorMessage = null;
$successMessage = null;
$httpStatus = 200;

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $submittedToken = $_POST['csrf_token'] ?? '';
  $action = $_POST['action'] ?? '';

  if (
    !is_string($submittedToken)
    || !is_string($action)
    || !hash_equals($_SESSION['stations_admin_csrf'], $submittedToken)
  ) {
    $httpStatus = 403;
    $errorMessage = 'リクエストを検証できませんでした。ページを再読み込みしてください。';
  } elseif ($action === 'login') {
    // すでにログイン済みの場合はログイン処理を受け付けない
    if ($isAuthenticated) {
      $successMessage = 'すでにログインしています。';
    } else {
      $submittedPassword = $_POST['password'] ?? '';

      global $env;

      if ($submittedPassword === getConfigValue('STATIONS_ADMIN_PASSWORD', $env)) {
        session_regenerate_id(true);

        $_SESSION['stations_admin_authenticated'] = true;
        $isAuthenticated = true;

        $successMessage = 'ログインしました。';
      } else {
        $errorMessage = 'パスワードが正しくありません。';
      }
    }
  } elseif ($action === 'logout') {
    if (!$isAuthenticated) {
      $errorMessage = 'ログインしていません。';
    } else {
      unset($_SESSION['stations_admin_authenticated']);

      session_regenerate_id(true);

      $isAuthenticated = false;
      $successMessage = 'ログアウトしました。';
    }
  } elseif ($action === 'update') {
    if (!$isAuthenticated) {
      $httpStatus = 403;
      $errorMessage = '更新するにはログインが必要です。';
    } else {
      require_once __DIR__ . '/import.php';

      try {
        $result = runStationsImport();

        if (!empty($result['success'])) {
          $successMessage = 'CSVの検証と更新が完了しました。';
        }
      } catch (Throwable $e) {
        error_log('[stations import] ' . $e);

        $errorMessage = '処理中にエラーが発生しました。サーバーのエラーログを確認してください。';
      }
    }
  } else {
    $httpStatus = 400;
    $errorMessage = '不正なリクエストです。';
  }

  // POSTごとにCSRFトークンを更新
  $_SESSION['stations_admin_csrf'] = bin2hex(random_bytes(32));

  http_response_code($httpStatus);
}

$csrfToken = $_SESSION['stations_admin_csrf'];
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>駅マスタ更新</title>
  <style>
    body {
      font-family: sans-serif;
      max-width: 760px;
      margin: 40px auto;
      padding: 0 16px;
      line-height: 1.6;
    }

    input[type="password"] {
      box-sizing: border-box;
      width: 100%;
      max-width: 400px;
      padding: 10px;
    }

    button {
      padding: 10px 18px;
      cursor: pointer;
    }

    .warning {
      padding: 12px;
      background: #fff3cd;
      border: 1px solid #e0c36b;
    }

    .success {
      padding: 12px;
      background: #e8f5e9;
      border: 1px solid #80b985;
      overflow-wrap: anywhere;
    }

    .error {
      padding: 12px;
      background: #fdecea;
      border: 1px solid #d98b84;
      overflow-wrap: anywhere;
    }

    code {
      overflow-wrap: anywhere;
    }

    .logout {
      margin-bottom: 24px;
    }
  </style>
</head>
<body>
  <h1>駅マスタ更新</h1>

  <?php if ($successMessage !== null): ?>
    <div class="success">
      <?= h($successMessage) ?>
    </div>
  <?php endif; ?>

  <?php if ($errorMessage !== null): ?>
    <div class="error">
      <?= h($errorMessage) ?>
    </div>
  <?php endif; ?>

  <?php if (!$isAuthenticated): ?>
    <h2>管理者ログイン</h2>

    <p>管理用パスワードを入力してください。</p>

    <form method="post">
      <input
        type="hidden"
        name="csrf_token"
        value="<?= h($csrfToken) ?>"
      >
      <input type="hidden" name="action" value="login">

      <p>
        <label for="password">パスワード</label><br>
        <input
          type="password"
          id="password"
          name="password"
          autocomplete="current-password"
          required
        >
      </p>

      <p>
        <button type="submit">ログイン</button>
      </p>
    </form>

  <?php else: ?>
    <form method="post" class="logout">
      <input
        type="hidden"
        name="csrf_token"
        value="<?= h($csrfToken) ?>"
      >
      <input type="hidden" name="action" value="logout">

      <button type="submit">ログアウト</button>
    </form>

    <h2>CSVによる駅マスタ更新</h2>

    <p>次のCSVファイルを検証し、すべて正常な場合に限り駅マスタを更新します。</p>

    <ul>
      <li><code>csv/station*.csv</code></li>
      <li><code>csv/line*.csv</code></li>
      <li><code>csv/join*.csv</code></li>
    </ul>

    <p class="warning">
      更新時は、駅・路線・駅間接続の既存レコードを全削除し、CSVの内容を全件登録します。<br>
      CSVの検証やDB登録に失敗した場合は、DBトランザクションにより更新をロールバックする設計です。
    </p>

    <?php if (is_array($result)): ?>
      <?php if (!empty($result['success'])): ?>
        <div class="success">
          <h2>更新完了</h2>
          <ul>
            <li>
              駅データ：
              <?= (int) $result['counts']['stations'] ?> 件
            </li>
            <li>
              路線データ：
              <?= (int) $result['counts']['lines'] ?> 件
            </li>
            <li>
              駅間接続データ：
              <?= (int) $result['counts']['joins'] ?> 件
            </li>
          </ul>

          <p>
            駅・路線・駅間接続データは、既存レコードを全削除したうえで、
            CSVの内容を全件登録しました。
          </p>
        </div>
      <?php else: ?>
        <div class="error">
          <h2>検証エラー：DBは更新していません</h2>

          <p>
            エラー <?= (int) $result['error_count'] ?> 件
          </p>

          <ul>
            <?php foreach ($result['errors'] as $error): ?>
              <li><?= h((string) $error) ?></li>
            <?php endforeach; ?>
          </ul>

          <?php if ($result['error_count'] > count($result['errors'])): ?>
            <p>表示は先頭50件までです。</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <form
      method="post"
      onsubmit="return confirm('CSVを検証し、問題がなければ駅・路線・駅間接続の既存データを全件置き換えます。実行しますか？')"
    >
      <input
        type="hidden"
        name="csrf_token"
        value="<?= h($csrfToken) ?>"
      >
      <input type="hidden" name="action" value="update">

      <p>
        <button type="submit">CSVを検証して更新</button>
      </p>
    </form>
  <?php endif; ?>
</body>
</html>
