USE mavambo_school;

CREATE TABLE IF NOT EXISTS school_expenses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category ENUM('Utilities','Repairs and maintenance','Learning materials','Transport','Food and boarding','Examinations','Administration','Communication','Security','Other') NOT NULL,
  supplier VARCHAR(180) NOT NULL,
  description VARCHAR(500) NOT NULL,
  invoice_reference VARCHAR(100) NULL,
  amount DECIMAL(14,2) NOT NULL,
  currency ENUM('USD','ZiG') NOT NULL,
  expense_date DATE NOT NULL,
  status ENUM('Submitted','Approved','Rejected','Paid') NOT NULL DEFAULT 'Submitted',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_note VARCHAR(500) NULL,
  paid_by BIGINT UNSIGNED NULL,
  paid_at DATETIME NULL,
  payment_method ENUM('Cash','EcoCash','Bank transfer','Swipe','Other') NULL,
  payment_reference VARCHAR(100) NULL,
  INDEX idx_school_expense_date_status (expense_date,status,currency),
  INDEX idx_school_expense_creator (created_by,created_at),
  CONSTRAINT fk_school_expense_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_school_expense_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_school_expense_payer FOREIGN KEY (paid_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
