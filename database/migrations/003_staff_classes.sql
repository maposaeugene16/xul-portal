USE mavambo_school;

CREATE TABLE IF NOT EXISTS staff_profiles (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  staff_number VARCHAR(40) NULL UNIQUE,
  job_title VARCHAR(100) NULL,
  department VARCHAR(100) NULL,
  phone VARCHAR(30) NULL,
  joined_on DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE users ADD COLUMN must_change_password BOOLEAN NOT NULL DEFAULT FALSE;

CREATE TABLE IF NOT EXISTS school_profile (
  id TINYINT UNSIGNED PRIMARY KEY,
  school_name VARCHAR(180) NOT NULL,
  school_code VARCHAR(40) NULL,
  school_type VARCHAR(40) NOT NULL DEFAULT 'Government',
  province VARCHAR(60) NULL,
  district VARCHAR(80) NULL,
  address TEXT NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(190) NULL,
  website VARCHAR(190) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO school_profile (id,school_name,school_code,school_type,province) VALUES (1,'Mavambo Secondary School','MVS-001','Government','Harare');

CREATE TABLE IF NOT EXISTS class_subjects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  teacher_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_class_subject (class_id,subject_id),
  CONSTRAINT fk_class_subject_class FOREIGN KEY (class_id) REFERENCES class_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_class_subject_subject FOREIGN KEY (subject_id) REFERENCES subjects(id),
  CONSTRAINT fk_class_subject_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE class_groups
  ADD CONSTRAINT fk_class_teacher_user FOREIGN KEY (class_teacher_id) REFERENCES users(id) ON DELETE SET NULL;
