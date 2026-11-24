USE mavambo_school;

CREATE TABLE IF NOT EXISTS library_books (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  isbn VARCHAR(20) NULL UNIQUE,
  title VARCHAR(180) NOT NULL,
  author VARCHAR(160) NOT NULL,
  publisher VARCHAR(160) NULL,
  subject_area VARCHAR(100) NULL,
  shelf_location VARCHAR(60) NULL,
  publication_year SMALLINT UNSIGNED NULL,
  total_copies SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_library_title (title),
  INDEX idx_library_subject (subject_area),
  CONSTRAINT fk_library_book_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS library_loans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  issued_on DATE NOT NULL,
  due_on DATE NOT NULL,
  returned_on DATE NULL,
  issued_by BIGINT UNSIGNED NULL,
  returned_by BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_library_loan_due (returned_on,due_on),
  INDEX idx_library_loan_student (student_id,returned_on),
  INDEX idx_library_loan_book (book_id,returned_on),
  CONSTRAINT fk_library_loan_book FOREIGN KEY (book_id) REFERENCES library_books(id),
  CONSTRAINT fk_library_loan_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_library_loan_issuer FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_library_loan_receiver FOREIGN KEY (returned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
