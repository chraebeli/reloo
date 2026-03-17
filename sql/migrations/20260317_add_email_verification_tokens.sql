SET @has_email_verified_at := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'email_verified_at'
);

SET @sql_add_email_verified_at := IF(
  @has_email_verified_at = 0,
  'ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER rejected_by',
  'SELECT 1'
);
PREPARE stmt_add_email_verified_at FROM @sql_add_email_verified_at;
EXECUTE stmt_add_email_verified_at;
DEALLOCATE PREPARE stmt_add_email_verified_at;

SET @has_email_verified_idx := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND INDEX_NAME = 'idx_users_email_verified'
);

SET @sql_add_email_verified_idx := IF(
  @has_email_verified_idx = 0,
  'ALTER TABLE users ADD INDEX idx_users_email_verified (email_verified_at)',
  'SELECT 1'
);
PREPARE stmt_add_email_verified_idx FROM @sql_add_email_verified_idx;
EXECUTE stmt_add_email_verified_idx;
DEALLOCATE PREPARE stmt_add_email_verified_idx;

CREATE TABLE IF NOT EXISTS email_verifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_email_verifications_user_created (user_id, created_at),
  INDEX idx_email_verifications_expires (expires_at),
  UNIQUE KEY uq_email_verifications_token_hash (token_hash),
  CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

UPDATE users
SET email_verified_at = COALESCE(email_verified_at, created_at)
WHERE email_verified_at IS NULL
  AND created_at < NOW();
