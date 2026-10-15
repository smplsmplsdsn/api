CREATE TABLE IF NOT EXISTS standup_comedian_posts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  comedian_id BIGINT UNSIGNED NOT NULL,
  text TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_standup_comedian_posts_comedian_id
    FOREIGN KEY (comedian_id)
    REFERENCES standup_comedians (id)
    ON DELETE CASCADE,

  INDEX idx_standup_comedian_posts_comedian_created (
    comedian_id,
    created_at DESC
  ),

  INDEX idx_standup_comedian_posts_created (
    created_at DESC
  )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;