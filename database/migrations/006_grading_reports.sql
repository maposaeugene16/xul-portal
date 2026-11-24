USE mavambo_school;

CREATE TABLE IF NOT EXISTS grading_scales (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  level ENUM('O-Level','A-Level') NOT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_grading_scale (name,level),
  CONSTRAINT fk_grading_scale_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grading_bands (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scale_id BIGINT UNSIGNED NOT NULL,
  grade VARCHAR(16) NOT NULL,
  lower_percentage DECIMAL(5,2) NOT NULL,
  upper_percentage DECIMAL(5,2) NOT NULL,
  description VARCHAR(120) NULL,
  UNIQUE KEY uq_grading_band (scale_id,grade),
  INDEX idx_grading_band_range (scale_id,lower_percentage,upper_percentage),
  CONSTRAINT fk_grading_band_scale FOREIGN KEY (scale_id) REFERENCES grading_scales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS term_report_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  term_id BIGINT UNSIGNED NOT NULL,
  teacher_comment VARCHAR(500) NULL,
  head_comment VARCHAR(500) NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_term_report_comment (student_id,term_id),
  CONSTRAINT fk_report_comment_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_report_comment_term FOREIGN KEY (term_id) REFERENCES school_terms(id) ON DELETE CASCADE,
  CONSTRAINT fk_report_comment_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
