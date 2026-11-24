USE mavambo_school;

CREATE TABLE IF NOT EXISTS timetable_slots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  term_id BIGINT UNSIGNED NOT NULL,
  class_id BIGINT UNSIGNED NOT NULL,
  class_subject_id BIGINT UNSIGNED NOT NULL,
  teacher_id BIGINT UNSIGNED NULL,
  weekday TINYINT UNSIGNED NOT NULL,
  period_number TINYINT UNSIGNED NOT NULL,
  starts_at TIME NOT NULL,
  ends_at TIME NOT NULL,
  venue VARCHAR(80) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_timetable_class_period (term_id,class_id,weekday,period_number),
  UNIQUE KEY uq_timetable_teacher_period (term_id,teacher_id,weekday,period_number),
  INDEX idx_timetable_teacher_day (term_id,teacher_id,weekday,starts_at,ends_at),
  INDEX idx_timetable_class_day (term_id,class_id,weekday,starts_at),
  CONSTRAINT fk_timetable_term FOREIGN KEY (term_id) REFERENCES school_terms(id),
  CONSTRAINT fk_timetable_class FOREIGN KEY (class_id) REFERENCES class_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_timetable_class_subject FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE CASCADE,
  CONSTRAINT fk_timetable_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_timetable_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
