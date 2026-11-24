USE mavambo_school;

CREATE TABLE IF NOT EXISTS student_incidents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  incident_on DATE NOT NULL,
  category ENUM('Discipline','Bullying','Attendance','Wellbeing','Safety','Property damage','Academic integrity','Other') NOT NULL,
  severity ENUM('Low','Moderate','High','Critical') NOT NULL DEFAULT 'Low',
  description TEXT NOT NULL,
  immediate_action TEXT NULL,
  status ENUM('Reported','Under Review','Resolved') NOT NULL DEFAULT 'Reported',
  assigned_to BIGINT UNSIGNED NULL,
  resolution TEXT NULL,
  guardian_contacted_on DATE NULL,
  guardian_contact_method ENUM('Phone','Meeting','Written','Other') NULL,
  guardian_contact_notes VARCHAR(500) NULL,
  reported_by BIGINT UNSIGNED NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_incident_student_date (student_id,incident_on),
  INDEX idx_incident_status_date (status,incident_on),
  INDEX idx_incident_reporter (reported_by,created_at),
  CONSTRAINT fk_incident_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_incident_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_incident_reporter FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_incident_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
