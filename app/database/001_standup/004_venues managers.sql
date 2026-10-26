CREATE TABLE IF NOT EXISTS standup_venue_managers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  venue_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_standup_venue_managers_venue_user (venue_id, user_id),
  KEY idx_standup_venue_managers_user_id (user_id),

  CONSTRAINT fk_standup_venue_managers_venue_id
    FOREIGN KEY (venue_id)
    REFERENCES standup_venues (id)
    ON DELETE CASCADE,

  CONSTRAINT fk_standup_venue_managers_user_id
    FOREIGN KEY (user_id)
    REFERENCES users (id)
    ON DELETE CASCADE
);