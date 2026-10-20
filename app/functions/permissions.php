
<?php

/**
 * memberがactiveで、指定したロールもactiveか確認する
 */
function hasActiveRole(
  PDO $pdo,
  $user_id,
  string $service_key,
  string $role
): bool {

  $stmt = $pdo->prepare('
    SELECT 1
    FROM user_service_roles target
    WHERE target.user_id = ?
      AND target.service_key = ?
      AND target.role = ?
      AND target.status = ?
      AND EXISTS (
        SELECT 1
        FROM user_service_roles member
        WHERE member.user_id = target.user_id
          AND member.service_key = target.service_key
          AND member.role = ?
          AND member.status = ?
      )
    LIMIT 1
  ');

  $stmt->execute([
    $user_id,
    $service_key,
    $role,
    'active',
    'member',
    'active',
  ]);

  return (bool) $stmt->fetchColumn();
}


/**
 * comedianがactiveか確認する
 */
function isActiveComedianById (
  PDO $pdo,
  $comedian_id
): bool {

  // 値が1以上の整数か確認する
  $comedian_id = filter_var(
    $comedian_id,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
  );

  if ($comedian_id === false) {
    return false;
  }

  $stmt = $pdo->prepare('
    SELECT 1
    FROM standup_comedians
    WHERE id = ?
      AND status = ?
    LIMIT 1
  ');

  $stmt->execute([
    $comedian_id,
    'active',
  ]);

  return (bool) $stmt->fetchColumn();
}


/**
 * activeなcomedianのIDを取得する
 */
function getActiveComedianId (
  PDO $pdo,
  $user_id
): ?int {

  if (!hasActiveRole(
    $pdo,
    $user_id,
    'standup',
    'comedian'
  )) {
    return null;
  }

  $stmt = $pdo->prepare('
    SELECT id
    FROM standup_comedians
    WHERE user_id = ?
      AND status = ?
    LIMIT 1
  ');

  $stmt->execute([
    $user_id,
    'active',
  ]);

  $comedian_id = $stmt->fetchColumn();

  return $comedian_id !== false
    ? (int) $comedian_id
    : null;
}


/**
 * memberがactiveか確認する
 */
function hasActiveMember(
  PDO $pdo,
  $user_id,
  string $service_key
): bool {

  $stmt = $pdo->prepare('
    SELECT 1
    FROM user_service_roles
    WHERE user_id = ?
      AND service_key = ?
      AND role = ?
      AND status = ?
    LIMIT 1
  ');

  $stmt->execute([
    $user_id,
    $service_key,
    'member',
    'active',
  ]);

  return (bool) $stmt->fetchColumn();
}