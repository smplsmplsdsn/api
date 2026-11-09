<?php

/**
 * Remember Token 設定
 */
const REMEMBER_COOKIE_NAME = 'remember_token';
const REMEMBER_TOKEN_LIFETIME = 60 * 60 * 24 * 90;


/**
 * Remember Token Cookieを発行
 */
function setRememberCookie(string $selector, string $validator, bool $isSecure): void {
  setcookie(REMEMBER_COOKIE_NAME, $selector . '.' . $validator, [
    'expires' => time() + REMEMBER_TOKEN_LIFETIME,
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => $isSecure ? 'None' : 'Lax',
  ]);
}


/**
 * Remember Token Cookieを削除
 */
function clearRememberCookie(bool $isSecure): void {
  setcookie(REMEMBER_COOKIE_NAME, '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => $isSecure ? 'None' : 'Lax',
  ]);
}


/**
 * Remember Tokenを新規発行
 */
function createRememberToken(PDO $pdo, int $userId, bool $isSecure): void {
  $selector = bin2hex(random_bytes(16));
  $validator = bin2hex(random_bytes(32));
  $validatorHash = hash('sha256', $validator);

  $stmt = $pdo->prepare('
    INSERT INTO user_remember_tokens (
      user_id,
      selector,
      validator_hash,
      expires_at
    ) VALUES (
      :user_id,
      :selector,
      :validator_hash,
      DATE_ADD(NOW(), INTERVAL 90 DAY)
    )
  ');

  $stmt->execute([
    ':user_id' => $userId,
    ':selector' => $selector,
    ':validator_hash' => $validatorHash,
  ]);

  setRememberCookie($selector, $validator, $isSecure);
}


/**
 * Remember Tokenでログイン状態を復元
 */
function restoreRememberToken(PDO $pdo, string $serviceKey, bool $isSecure): bool {
  $cookie = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';

  if (!preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/', $cookie, $matches)) {
    return false;
  }

  $selector = $matches[1];
  $validator = $matches[2];

  try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('
      SELECT
        rt.id,
        rt.user_id,
        rt.validator_hash
      FROM user_remember_tokens rt

      INNER JOIN users u
        ON u.id = rt.user_id

      INNER JOIN user_service_roles usr
        ON usr.user_id = u.id

      WHERE rt.selector = :selector
        AND rt.expires_at > NOW()
        AND u.status = "active"
        AND usr.service_key = :service_key
        AND usr.role = "member"
        AND usr.status = "active"

      LIMIT 1
      FOR UPDATE
    ');

    $stmt->execute([
      ':selector' => $selector,
      ':service_key' => $serviceKey,
    ]);

    $token = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
      !$token ||
      !hash_equals($token['validator_hash'], hash('sha256', $validator))
    ) {
      $pdo->rollBack();
      clearRememberCookie($isSecure);
      return false;
    }

    $newValidator = rotateRememberToken(
      $pdo,
      (int) $token['id']
    );

    $pdo->commit();

    $_SESSION['services'][$serviceKey]['user_id'] = (int) $token['user_id'];

    setRememberCookie(
      $selector,
      $newValidator,
      $isSecure
    );

    return true;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }

    throw $e;
  }
}


/**
 * Remember TokenのValidatorを更新
 */
function rotateRememberToken(
  PDO $pdo,
  int $tokenId
): string {
  $validator = bin2hex(random_bytes(32));
  $validatorHash = hash('sha256', $validator);

  $stmt = $pdo->prepare('
    UPDATE user_remember_tokens
    SET
      validator_hash = :validator_hash,
      expires_at = DATE_ADD(NOW(), INTERVAL 90 DAY),
      last_used_at = NOW()
    WHERE id = :id
  ');

  $stmt->execute([
    ':validator_hash' => $validatorHash,
    ':id' => $tokenId,
  ]);

  return $validator;
}

/**
 * Remember Tokenを削除
 */
function deleteRememberToken(PDO $pdo, ?int $userId = null): void {
  $cookie = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';

  if (!preg_match('/^([a-f0-9]{32})\.[a-f0-9]{64}$/', $cookie, $matches)) {
    return;
  }

  $selector = $matches[1];

  if ($userId !== null) {
    $stmt = $pdo->prepare('
      DELETE FROM user_remember_tokens
      WHERE selector = :selector
        AND user_id = :user_id
    ');

    $stmt->execute([
      ':selector' => $selector,
      ':user_id' => $userId,
    ]);

  } else {
    $stmt = $pdo->prepare('
      DELETE FROM user_remember_tokens
      WHERE selector = :selector
    ');

    $stmt->execute([
      ':selector' => $selector,
    ]);
  }
}
