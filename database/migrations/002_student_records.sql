USE mavambo_school;

CREATE TABLE IF NOT EXISTS student_guardians (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  relationship VARCHAR(40) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(160) NULL,
  is_primary BOOLEAN NOT NULL DEFAULT TRUE,
  consent_status ENUM('Pending','Granted','Revoked') NOT NULL DEFAULT 'Pending',
  consent_method ENUM('Written','Electronic','In person') NULL,
  consent_recorded_at DATETIME NULL,
  consent_reference VARCHAR(180) NULL,
  recorded_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_guardian_student (student_id, is_primary),
  CONSTRAINT fk_guardian_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_guardian_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_enrolments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  academic_year SMALLINT UNSIGNED NOT NULL,
  term_number TINYINT UNSIGNED NOT NULL,
  form_level TINYINT UNSIGNED NOT NULL,
  stream VARCHAR(20) NULL,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  status ENUM('Enrolled','Promoted','ClassChanged','Transferred','Withdrawn','Completed') NOT NULL DEFAULT 'Enrolled',
  change_reason VARCHAR(200) NULL,
  recorded_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_enrolment_student (student_id, start_date),
  INDEX idx_enrolment_year_form (academic_year, form_level, stream),
  CONSTRAINT fk_enrolment_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_enrolment_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO student_enrolments (student_id, academic_year, term_number, form_level, stream, start_date, status)
SELECT id, YEAR(CURDATE()), 3, form_level, stream, COALESCE(DATE(created_at), CURDATE()), 'Enrolled'
FROM students s
WHERE NOT EXISTS (SELECT 1 FROM student_enrolments e WHERE e.student_id=s.id AND e.end_date IS NULL);
