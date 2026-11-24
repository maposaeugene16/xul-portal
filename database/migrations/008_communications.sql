USE mavambo_school;

ALTER TABLE announcements
  ADD COLUMN IF NOT EXISTS status ENUM('Draft','Published','Archived') NOT NULL DEFAULT 'Draft' AFTER audience,
  ADD COLUMN IF NOT EXISTS priority ENUM('Normal','Important','Urgent') NOT NULL DEFAULT 'Normal' AFTER status,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE announcements SET status='Published' WHERE status='Draft' AND published_at IS NOT NULL;

CREATE TABLE IF NOT EXISTS announcement_reads (
  announcement_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (announcement_id,user_id),
  INDEX idx_announcement_reads_user (user_id,read_at),
  CONSTRAINT fk_announcement_read_announcement FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
  CONSTRAINT fk_announcement_read_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS school_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  venue VARCHAR(120) NULL,
  audience ENUM('Everyone','Staff','Guardians') NOT NULL DEFAULT 'Everyone',
  status ENUM('Draft','Published','Cancelled') NOT NULL DEFAULT 'Draft',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_school_event_start (status,starts_at),
  CONSTRAINT fk_school_event_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
