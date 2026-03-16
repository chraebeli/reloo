ALTER TABLE users
  ADD COLUMN approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER role,
  ADD COLUMN approved_at DATETIME NULL AFTER approval_status,
  ADD COLUMN approved_by INT UNSIGNED NULL AFTER approved_at,
  ADD COLUMN rejected_at DATETIME NULL AFTER approved_by,
  ADD COLUMN rejected_by INT UNSIGNED NULL AFTER rejected_at,
  ADD INDEX idx_users_approval_status_created (approval_status, created_at),
  ADD CONSTRAINT fk_users_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_users_rejected_by FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL;

UPDATE users
SET approval_status = 'approved'
WHERE approval_status IS NULL OR approval_status = '';
