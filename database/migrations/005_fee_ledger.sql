USE mavambo_school;

CREATE TABLE IF NOT EXISTS fee_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO fee_categories (code,name) VALUES
('TUITION','Tuition'),('DEVELOPMENT','Development levy'),('EXAM','Examination fees'),('BOARDING','Boarding'),('TRANSPORT','Transport'),('UNIFORM','Uniforms'),('OTHER','Other school charges');

CREATE TABLE IF NOT EXISTS fee_schedules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  term_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  form_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
  description VARCHAR(160) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency ENUM('USD','ZiG') NOT NULL,
  due_date DATE NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fee_schedule (term_id,category_id,form_level,currency),
  CONSTRAINT fk_schedule_term FOREIGN KEY (term_id) REFERENCES school_terms(id),
  CONSTRAINT fk_schedule_category FOREIGN KEY (category_id) REFERENCES fee_categories(id),
  CONSTRAINT fk_schedule_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fee_ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  term_id BIGINT UNSIGNED NULL,
  schedule_id BIGINT UNSIGNED NULL,
  category_id BIGINT UNSIGNED NULL,
  transaction_type ENUM('Charge','Payment','Adjustment') NOT NULL,
  description VARCHAR(180) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency ENUM('USD','ZiG') NOT NULL,
  exchange_rate DECIMAL(14,6) NULL,
  receipt_no VARCHAR(40) NULL UNIQUE,
  payment_method ENUM('Cash','EcoCash','Bank transfer','Swipe','Other') NULL,
  external_reference VARCHAR(100) NULL,
  transaction_date DATE NOT NULL,
  recorded_by BIGINT UNSIGNED NULL,
  reverses_id BIGINT UNSIGNED NULL UNIQUE,
  reversal_reason VARCHAR(200) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ledger_student_date (student_id,transaction_date,id),
  INDEX idx_ledger_term_currency (term_id,currency,transaction_type),
  UNIQUE KEY uq_schedule_student (schedule_id,student_id),
  CONSTRAINT fk_ledger_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_ledger_term FOREIGN KEY (term_id) REFERENCES school_terms(id),
  CONSTRAINT fk_ledger_schedule FOREIGN KEY (schedule_id) REFERENCES fee_schedules(id),
  CONSTRAINT fk_ledger_category FOREIGN KEY (category_id) REFERENCES fee_categories(id),
  CONSTRAINT fk_ledger_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ledger_reversal FOREIGN KEY (reverses_id) REFERENCES fee_ledger(id) ON DELETE SET NULL
) ENGINE=InnoDB;
