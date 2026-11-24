USE mavambo_school;

CREATE TABLE IF NOT EXISTS staff_leave_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id BIGINT UNSIGNED NOT NULL,
  leave_type ENUM('Annual','Sick','Maternity','Paternity','Compassionate','Study','Unpaid','Other') NOT NULL,
  start_on DATE NOT NULL,
  end_on DATE NOT NULL,
  reason VARCHAR(1000) NULL,
  status ENUM('Pending','Approved','Declined','Cancelled') NOT NULL DEFAULT 'Pending',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_staff_leave_range (staff_id,start_on,end_on),
  INDEX idx_staff_leave_review (status,start_on),
  CONSTRAINT fk_staff_leave_user FOREIGN KEY (staff_id) REFERENCES users(id),
  CONSTRAINT fk_staff_leave_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff_leave_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  leave_request_id BIGINT UNSIGNED NOT NULL,
  from_status ENUM('Pending','Approved','Declined','Cancelled') NULL,
  to_status ENUM('Pending','Approved','Declined','Cancelled') NOT NULL,
  notes VARCHAR(500) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_staff_leave_history (leave_request_id,created_at),
  CONSTRAINT fk_staff_leave_history_request FOREIGN KEY (leave_request_id) REFERENCES staff_leave_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_staff_leave_history_user FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff_attendance (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id BIGINT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  status ENUM('Present','Late','Absent','On Leave','Off Duty') NOT NULL,
  check_in TIME NULL,
  check_out TIME NULL,
  note VARCHAR(255) NULL,
  recorded_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_staff_attendance (staff_id,attendance_date),
  INDEX idx_staff_attendance_date (attendance_date,status),
  CONSTRAINT fk_staff_attendance_user FOREIGN KEY (staff_id) REFERENCES users(id),
  CONSTRAINT fk_staff_attendance_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
