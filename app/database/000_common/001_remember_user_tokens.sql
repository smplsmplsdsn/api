CREATE TABLE IF NOT EXISTS user_remember_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  selector CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  validator_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,

  UNIQUE KEY uq_selector (selector),
  KEY idx_user_id (user_id),
  KEY idx_expires_at (expires_at),

  CONSTRAINT fk_remember_tokens_user
    FOREIGN KEY (user_id)
    REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB;