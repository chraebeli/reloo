ALTER TABLE items
  ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
  ADD COLUMN deleted_by INT UNSIGNED NULL AFTER deleted_at,
  ADD COLUMN deleted_by_role ENUM('admin','member') NULL AFTER deleted_by,
  ADD COLUMN deletion_reason TEXT NULL AFTER deleted_by_role,
  ADD INDEX idx_items_deleted_at (deleted_at),
  ADD CONSTRAINT fk_items_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE item_deletion_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  item_title VARCHAR(180) NOT NULL,
  ownership_type ENUM('privat_verleihbar','privat_verschenken','privat_tausch','gemeinschaftlich') NOT NULL,
  deleted_by INT UNSIGNED NOT NULL,
  deleted_by_role ENUM('admin','member') NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  admin_reason TEXT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_item_deletion_log_item_created (item_id, created_at),
  INDEX idx_item_deletion_log_deleted_by (deleted_by, created_at),
  CONSTRAINT fk_item_deletion_log_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_deletion_log_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
