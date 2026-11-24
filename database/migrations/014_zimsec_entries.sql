USE mavambo_school;

CREATE TABLE IF NOT EXISTS exam_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exam_year SMALLINT UNSIGNED NOT NULL,
  level ENUM('O-Level','A-Level') NOT NULL,
  session_name VARCHAR(80) NOT NULL,
  entry_type VARCHAR(60) NOT NULL,
  centre_number VARCHAR(30) NOT NULL,
  opens_on DATE NOT NULL,
  entry_deadline DATE NOT NULL,
  status ENUM('Draft','Open','Closed') NOT NULL DEFAULT 'Draft',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_exam_session (exam_year,level,session_name,entry_type),
  INDEX idx_exam_open (status,exam_year,level),
  CONSTRAINT fk_exam_session_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_session_subjects (
  session_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  option_group VARCHAR(40) NULL,
  PRIMARY KEY (session_id,subject_id),
  CONSTRAINT fk_exam_offer_session FOREIGN KEY (session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_exam_offer_subject FOREIGN KEY (subject_id) REFERENCES subjects(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_candidates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  surname_snapshot VARCHAR(80) NOT NULL,
  first_names_snapshot VARCHAR(80) NOT NULL,
  sex_snapshot ENUM('F','M') NOT NULL,
  date_of_birth_snapshot DATE NULL,
  national_id_snapshot VARCHAR(30) NULL,
  candidate_type ENUM('Formal','Nonformal') NOT NULL DEFAULT 'Formal',
  payment_type ENUM('Self','BEAM') NOT NULL DEFAULT 'Self',
  nationality ENUM('Local','Territorial') NOT NULL DEFAULT 'Local',
  special_requirements VARCHAR(160) NULL,
  candidate_number VARCHAR(24) NULL,
  status ENUM('Draft','Submitted','Withdrawn') NOT NULL DEFAULT 'Draft',
  withdrawal_reason VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  submitted_by BIGINT UNSIGNED NULL,
  submitted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_exam_candidate (session_id,student_id),
  UNIQUE KEY uq_exam_candidate_number (session_id,candidate_number),
  INDEX idx_exam_candidate_status (session_id,status),
  CONSTRAINT fk_exam_candidate_session FOREIGN KEY (session_id) REFERENCES exam_sessions(id),
  CONSTRAINT fk_exam_candidate_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_exam_candidate_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_exam_candidate_submitter FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_candidate_subjects (
  candidate_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  is_repeat BOOLEAN NOT NULL DEFAULT FALSE,
  previous_year SMALLINT UNSIGNED NULL,
  previous_centre_number VARCHAR(30) NULL,
  previous_candidate_number VARCHAR(24) NULL,
  PRIMARY KEY (candidate_id,subject_id),
  CONSTRAINT fk_exam_candidate_subject_candidate FOREIGN KEY (candidate_id) REFERENCES exam_candidates(id) ON DELETE CASCADE,
  CONSTRAINT fk_exam_candidate_subject_subject FOREIGN KEY (subject_id) REFERENCES subjects(id)
) ENGINE=InnoDB;
