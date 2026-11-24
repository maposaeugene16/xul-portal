USE mavambo_school;

CREATE TABLE IF NOT EXISTS admission_applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_code VARCHAR(24) NULL UNIQUE,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  sex ENUM('F','M') NOT NULL,
  date_of_birth DATE NULL,
  national_id VARCHAR(30) NULL,
  intended_form TINYINT UNSIGNED NOT NULL,
  entry_year SMALLINT UNSIGNED NOT NULL,
  stream_preference VARCHAR(20) NULL,
  previous_school VARCHAR(180) NULL,
  guardian_name VARCHAR(160) NOT NULL,
  guardian_relationship VARCHAR(40) NOT NULL,
  guardian_phone VARCHAR(30) NOT NULL,
  guardian_email VARCHAR(160) NULL,
  address TEXT NULL,
  status ENUM('Submitted','Under Review','Waitlisted','Offered','Accepted','Declined','Enrolled') NOT NULL DEFAULT 'Submitted',
  offer_expires_on DATE NULL,
  student_id BIGINT UNSIGNED NULL UNIQUE,
  submitted_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_admission_status_year (status,entry_year),
  INDEX idx_admission_name (last_name,first_name),
  CONSTRAINT fk_admission_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
  CONSTRAINT fk_admission_submitter FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admission_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  from_status ENUM('Submitted','Under Review','Waitlisted','Offered','Accepted','Declined','Enrolled') NULL,
  to_status ENUM('Submitted','Under Review','Waitlisted','Offered','Accepted','Declined','Enrolled') NOT NULL,
  notes VARCHAR(2000) NULL,
  changed_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admission_history (application_id,created_at),
  CONSTRAINT fk_admission_history_application FOREIGN KEY (application_id) REFERENCES admission_applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_admission_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
