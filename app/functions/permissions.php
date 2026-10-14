
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
function isActiveComedian (
  PDO $pdo,
  $user_id
): bool {

  if (!hasActiveRole(
    $pdo,
    $user_id,
    'standup',
    'comedian'
  )) {
    return false;
  }

  $stmt = $pdo->prepare('
    SELECT 1
    FROM standup_comedians
    WHERE user_id = ?
      AND status = ?
    LIMIT 1
  ');

  $stmt->execute([
    $user_id,
    'active',
  ]);

  return (bool) $stmt->fetchColumn();
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