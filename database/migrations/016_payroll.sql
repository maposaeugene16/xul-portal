USE mavambo_school;

CREATE TABLE IF NOT EXISTS staff_pay_settings (
  staff_id BIGINT UNSIGNED PRIMARY KEY,
  base_salary DECIMAL(12,2) NOT NULL,
  currency ENUM('USD','ZiG') NOT NULL,
  effective_from DATE NOT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_setting_staff FOREIGN KEY (staff_id) REFERENCES users(id),
  CONSTRAINT fk_pay_setting_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payroll_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  currency ENUM('USD','ZiG') NOT NULL,
  status ENUM('Draft','Approved','Paid','Voided') NOT NULL DEFAULT 'Draft',
  created_by BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  paid_by BIGINT UNSIGNED NULL,
  paid_at DATETIME NULL,
  payment_reference VARCHAR(120) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payroll_period_currency (period_start,period_end,currency),
  INDEX idx_payroll_period (period_start,status),
  CONSTRAINT fk_payroll_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_payroll_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_payroll_payer FOREIGN KEY (paid_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payroll_lines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  staff_id BIGINT UNSIGNED NOT NULL,
  staff_name VARCHAR(160) NOT NULL,
  staff_number VARCHAR(40) NULL,
  job_title VARCHAR(100) NULL,
  base_salary DECIMAL(12,2) NOT NULL,
  currency ENUM('USD','ZiG') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payroll_staff (run_id,staff_id),
  CONSTRAINT fk_payroll_line_run FOREIGN KEY (run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_line_staff FOREIGN KEY (staff_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payroll_line_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payroll_line_id BIGINT UNSIGNED NOT NULL,
  item_type ENUM('Earning','Deduction') NOT NULL,
  description VARCHAR(120) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_payroll_items_line (payroll_line_id,item_type),
  CONSTRAINT fk_payroll_item_line FOREIGN KEY (payroll_line_id) REFERENCES payroll_lines(id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_item_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
