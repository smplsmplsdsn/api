CREATE TABLE IF NOT EXISTS standup_comedians (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL DEFAULT '',
  thumbnail varchar(500) NOT NULL DEFAULT '',
  instagram varchar(255) NOT NULL DEFAULT '',
  tiktok varchar(255) NOT NULL DEFAULT '',
  youtube varchar(255) NOT NULL DEFAULT '',
  x varchar(255) NOT NULL DEFAULT '',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_standup_comedians_user_id (user_id),

  CONSTRAINT fk_standup_comedians_user_id
    FOREIGN KEY (user_id)
    REFERENCES users (id)
    ON DELETE CASCADE
);