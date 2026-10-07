CREATE TABLE IF NOT EXISTS standup_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  comedian_id BIGINT UNSIGNED NULL,
  text TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_standup_comments_user_id
    FOREIGN KEY (user_id)
    REFERENCES users (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_standup_comments_comedian_id
    FOREIGN KEY (comedian_id)
    REFERENCES standup_comedians (id)
    ON DELETE RESTRICT,
  INDEX idx_standup_comments_user_created (
    user_id,
    created_at DESC
  ),
  INDEX idx_standup_comments_comedian_created (
    comedian_id,
    created_at DESC
  ),
  INDEX idx_standup_comments_created (
    created_at DESC
  )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;