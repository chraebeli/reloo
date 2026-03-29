ALTER TABLE users
  ADD COLUMN pending_email VARCHAR(190) NULL AFTER email,
  ADD INDEX idx_users_pending_email (pending_email);

CREATE TABLE user_passkeys (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  label VARCHAR(120) NOT NULL,
  credential_id VARCHAR(255) NOT NULL,
  public_key_spki TEXT NOT NULL,
  sign_count INT UNSIGNED NOT NULL DEFAULT 0,
  transports VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  last_used_at DATETIME NULL,
  UNIQUE KEY uq_user_passkeys_credential (credential_id),
  INDEX idx_user_passkeys_user_created (user_id, created_at),
  CONSTRAINT fk_user_passkeys_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
