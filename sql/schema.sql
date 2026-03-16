CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(40) NULL,
  location VARCHAR(120) NULL,
  bio TEXT NULL,
  role ENUM('admin','member') NOT NULL DEFAULT 'member',
  approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  approved_at DATETIME NULL,
  approved_by INT UNSIGNED NULL,
  rejected_at DATETIME NULL,
  rejected_by INT UNSIGNED NULL,
  email_verified_at DATETIME NULL,
  password_reset_token VARCHAR(128) NULL,
  password_reset_expires_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_users_role_created (role, created_at),
  INDEX idx_users_approval_status_created (approval_status, created_at),
  INDEX idx_users_reset_expires (password_reset_expires_at),
  CONSTRAINT fk_users_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_users_rejected_by FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `groups` (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  invite_code VARCHAR(32) NOT NULL UNIQUE,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  CONSTRAINT fk_groups_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE group_members (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  role ENUM('admin','member') NOT NULL DEFAULT 'member',
  joined_at DATETIME NOT NULL,
  UNIQUE KEY uq_group_member (group_id, user_id),
  CONSTRAINT fk_group_members_group FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
  CONSTRAINT fk_group_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  item_condition VARCHAR(40) NOT NULL,
  ownership_type ENUM('privat_verleihbar','privat_verschenken','privat_tausch','gemeinschaftlich') NOT NULL,
  location_text VARCHAR(160) NULL,
  availability_status ENUM('verfügbar','angefragt','reserviert','ausgeliehen','zurückgegeben','in_reparatur','deaktiviert') NOT NULL DEFAULT 'verfügbar',
  deposit_note VARCHAR(255) NULL,
  tags VARCHAR(255) NULL,
  visibility ENUM('group_internal','public') NOT NULL DEFAULT 'group_internal',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_items_group (group_id),
  INDEX idx_items_owner (owner_id),
  CONSTRAINT fk_items_group FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE item_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_item_images_item_created (item_id, created_at),
  CONSTRAINT fk_item_images_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE item_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  requester_id INT UNSIGNED NOT NULL,
  request_type ENUM('ausleihe','geschenk','tausch') NOT NULL,
  status ENUM('angefragt','reserviert','abgelehnt','abgeschlossen') NOT NULL,
  message TEXT NULL,
  requested_start_date DATE NULL,
  requested_end_date DATE NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_item_requests_item_status (item_id, status),
  CONSTRAINT fk_item_requests_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_requests_requester FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE loans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  request_id INT UNSIGNED NULL,
  lender_id INT UNSIGNED NOT NULL,
  borrower_id INT UNSIGNED NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  returned_at DATETIME NULL,
  status ENUM('ausgeliehen','zurückgegeben') NOT NULL DEFAULT 'ausgeliehen',
  created_at DATETIME NOT NULL,
  INDEX idx_loans_status_created (status, created_at),
  INDEX idx_loans_lender_status (lender_id, status),
  INDEX idx_loans_borrower_status (borrower_id, status),
  CONSTRAINT fk_loans_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  CONSTRAINT fk_loans_request FOREIGN KEY (request_id) REFERENCES item_requests(id) ON DELETE SET NULL,
  CONSTRAINT fk_loans_lender FOREIGN KEY (lender_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_loans_borrower FOREIGN KEY (borrower_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE repairs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  reported_by INT UNSIGNED NOT NULL,
  status ENUM('gemeldet','in_pruefung','in_reparatur','repariert','nicht_reparierbar') NOT NULL,
  issue_description TEXT NOT NULL,
  part_notes TEXT NULL,
  effort_notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  INDEX idx_repairs_status_created (status, created_at),
  CONSTRAINT fk_repairs_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  CONSTRAINT fk_repairs_reported FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  channel ENUM('in_app','email') NOT NULL,
  subject VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_notifications_user_read (user_id, is_read, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  group_id INT UNSIGNED NULL,
  activity_type VARCHAR(60) NOT NULL,
  message VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_activity_group (group_id),
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_activity_group FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (name, created_at) VALUES
('Werkzeuge', NOW()), ('Küchengeräte', NOW()), ('Camping', NOW()), ('Möbel', NOW()),
('Elektronik', NOW()), ('Kinderartikel', NOW()), ('Kleidung', NOW()), ('Bücher / Medien', NOW()), ('Sonstiges', NOW());
