USE mavambo_school;

CREATE TABLE IF NOT EXISTS school_activities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  activity_name VARCHAR(140) NOT NULL,
  category ENUM('Sport','Club','Academic','Culture','Service','Other') NOT NULL,
  description VARCHAR(1000) NULL,
  academic_year SMALLINT UNSIGNED NOT NULL,
  patron_id BIGINT UNSIGNED NULL,
  capacity SMALLINT UNSIGNED NULL,
  status ENUM('Active','Closed') NOT NULL DEFAULT 'Active',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_school_activity_year (activity_name,academic_year),
  INDEX idx_school_activity_status (academic_year,status,category),
  CONSTRAINT fk_school_activity_patron FOREIGN KEY (patron_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_school_activity_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_memberships (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  activity_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  joined_on DATE NOT NULL,
  left_on DATE NULL,
  status ENUM('Active','Ended') NOT NULL DEFAULT 'Active',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_membership_activity (activity_id,status,student_id),
  INDEX idx_activity_membership_student (student_id,status,activity_id),
  CONSTRAINT fk_activity_member_activity FOREIGN KEY (activity_id) REFERENCES school_activities(id),
  CONSTRAINT fk_activity_member_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_activity_member_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  activity_id BIGINT UNSIGNED NOT NULL,
  session_type ENUM('Practice','Meeting','Fixture','Competition','Trip','Other') NOT NULL DEFAULT 'Practice',
  title VARCHAR(160) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  venue VARCHAR(160) NULL,
  opponent VARCHAR(140) NULL,
  result VARCHAR(100) NULL,
  notes VARCHAR(500) NULL,
  status ENUM('Scheduled','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_session_date (activity_id,starts_at,status),
  CONSTRAINT fk_activity_session_activity FOREIGN KEY (activity_id) REFERENCES school_activities(id),
  CONSTRAINT fk_activity_session_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_session_attendance (
  session_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  status ENUM('Present','Absent','Excused') NOT NULL,
  note VARCHAR(255) NULL,
  recorded_by BIGINT UNSIGNED NULL,
  recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (session_id,student_id),
  INDEX idx_activity_session_attendance_student (student_id,session_id),
  CONSTRAINT fk_activity_attendance_session FOREIGN KEY (session_id) REFERENCES activity_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_activity_attendance_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_activity_attendance_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
