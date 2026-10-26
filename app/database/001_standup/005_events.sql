-- ============================================
-- standup_events
-- ============================================

CREATE TABLE IF NOT EXISTS standup_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  creator_public_id CHAR(26) NOT NULL,
  type VARCHAR(20) NOT NULL DEFAULT '',
  name VARCHAR(255) NOT NULL DEFAULT '',
  message TEXT NOT NULL DEFAULT (''),
  message_for_comedian TEXT NOT NULL DEFAULT (''),
  image VARCHAR(500) NOT NULL DEFAULT '',
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  confirmed_candidate_id BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_standup_events_creator_public_id
    FOREIGN KEY (creator_public_id)
    REFERENCES users (public_id)
    ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- standup_event_candidates
-- ============================================

CREATE TABLE IF NOT EXISTS standup_event_candidates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  venue_id BIGINT UNSIGNED DEFAULT NULL,
  open_at DATETIME DEFAULT NULL,
  start_at DATETIME DEFAULT NULL,
  fee TEXT NOT NULL DEFAULT (''),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_standup_event_candidates_event_id
    FOREIGN KEY (event_id)
    REFERENCES standup_events (id)
    ON DELETE CASCADE,

  CONSTRAINT fk_standup_event_candidates_venue_id
    FOREIGN KEY (venue_id)
    REFERENCES standup_venues (id)
    ON DELETE RESTRICT
);


-- ============================================
-- standup_event_candidate_comedians
-- ============================================

CREATE TABLE IF NOT EXISTS standup_event_candidate_comedians (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidate_id BIGINT UNSIGNED NOT NULL,
  comedian_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uk_standup_event_candidate_comedians (
    candidate_id,
    comedian_id
  ),

  CONSTRAINT fk_standup_event_candidate_comedians_candidate_id
    FOREIGN KEY (candidate_id)
    REFERENCES standup_event_candidates (id)
    ON DELETE CASCADE,

  CONSTRAINT fk_standup_event_candidate_comedians_comedian_id
    FOREIGN KEY (comedian_id)
    REFERENCES standup_comedians (id)
    ON DELETE CASCADE
);


-- ============================================
-- confirmed_candidate_id
-- ============================================

SET @constraint_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'standup_events'
    AND CONSTRAINT_NAME = 'fk_standup_events_confirmed_candidate_id'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql := IF(
  @constraint_exists = 0,
  'ALTER TABLE standup_events ADD CONSTRAINT fk_standup_events_confirmed_candidate_id FOREIGN KEY (confirmed_candidate_id) REFERENCES standup_event_candidates (id) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;