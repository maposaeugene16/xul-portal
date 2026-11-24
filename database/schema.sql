CREATE DATABASE IF NOT EXISTS mavambo_school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mavambo_school;

CREATE TABLE IF NOT EXISTS students (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admission_no VARCHAR(30) NOT NULL UNIQUE,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  national_id VARCHAR(30) NULL,
  sex ENUM('F','M') NOT NULL,
  date_of_birth DATE NULL,
  form_level TINYINT UNSIGNED NOT NULL,
  stream VARCHAR(20) NULL,
  status ENUM('Active','Transferred','Withdrawn','Graduated') NOT NULL DEFAULT 'Active',
  guardian_name VARCHAR(160) NULL,
  guardian_phone VARCHAR(30) NULL,
  guardian_email VARCHAR(160) NULL,
  address TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_students_form (form_level, stream), INDEX idx_students_name (last_name, first_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS school_terms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year SMALLINT UNSIGNED NOT NULL,
  term_number TINYINT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  starts_on DATE NOT NULL,
  ends_on DATE NOT NULL,
  is_current BOOLEAN NOT NULL DEFAULT FALSE,
  UNIQUE KEY uq_school_term (academic_year, term_number)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subjects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_code VARCHAR(20) NOT NULL UNIQUE,
  subject_name VARCHAR(100) NOT NULL,
  level ENUM('O-Level','A-Level','Both') NOT NULL DEFAULT 'Both',
  active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS class_groups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  form_level TINYINT UNSIGNED NOT NULL,
  stream VARCHAR(20) NOT NULL,
  academic_year SMALLINT UNSIGNED NOT NULL,
  class_teacher_id BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_class_stream (form_level, stream, academic_year)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('Administrator','Head','Teacher','Bursar','Clerk','Guardian') NOT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  must_change_password BOOLEAN NOT NULL DEFAULT FALSE,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  status ENUM('Present','Absent','Late','Excused') NOT NULL,
  note VARCHAR(255) NULL,
  recorded_by BIGINT UNSIGNED NULL,
  recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_attendance (student_id, attendance_date),
  INDEX idx_attendance_date (attendance_date, status),
  CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assessments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  term_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  class_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  assessment_type ENUM('Test','Assignment','Practical','Coursework','Examination') NOT NULL,
  max_mark DECIMAL(7,2) NOT NULL,
  held_on DATE NULL,
  CONSTRAINT fk_assessment_term FOREIGN KEY (term_id) REFERENCES school_terms(id),
  CONSTRAINT fk_assessment_subject FOREIGN KEY (subject_id) REFERENCES subjects(id),
  CONSTRAINT fk_assessment_class FOREIGN KEY (class_id) REFERENCES class_groups(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assessment_marks (
  assessment_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  mark DECIMAL(7,2) NULL,
  mark_status ENUM('Scored','Absent','Exempt') NOT NULL DEFAULT 'Scored',
  grade VARCHAR(4) NULL,
  comment VARCHAR(255) NULL,
  PRIMARY KEY (assessment_id, student_id),
  CONSTRAINT fk_mark_assessment FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
  CONSTRAINT fk_mark_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fee_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL UNIQUE,
  balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  currency ENUM('USD','ZiG') NOT NULL DEFAULT 'USD',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_fee_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fee_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  transaction_type ENUM('Charge','Payment','Adjustment') NOT NULL,
  description VARCHAR(160) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency ENUM('USD','ZiG') NOT NULL,
  exchange_rate DECIMAL(12,4) NULL,
  receipt_no VARCHAR(40) NULL UNIQUE,
  payment_method VARCHAR(40) NULL,
  transaction_date DATE NOT NULL,
  recorded_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fee_student_date (student_id, transaction_date),
  CONSTRAINT fk_transaction_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

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

ALTER TABLE class_groups
  ADD CONSTRAINT fk_class_teacher_user FOREIGN KEY (class_teacher_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS announcements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  audience ENUM('Everyone','Staff','Guardians','Learners') NOT NULL DEFAULT 'Everyone',
  status ENUM('Draft','Published','Archived') NOT NULL DEFAULT 'Draft',
  priority ENUM('Normal','Important','Urgent') NOT NULL DEFAULT 'Normal',
  published_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_announcement_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS announcement_reads (
  announcement_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (announcement_id,user_id),
  INDEX idx_announcement_reads_user (user_id,read_at),
  CONSTRAINT fk_announcement_read_announcement FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
  CONSTRAINT fk_announcement_read_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS school_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  venue VARCHAR(120) NULL,
  audience ENUM('Everyone','Staff','Guardians') NOT NULL DEFAULT 'Everyone',
  status ENUM('Draft','Published','Cancelled') NOT NULL DEFAULT 'Draft',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_school_event_start (status,starts_at),
  CONSTRAINT fk_school_event_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_created (created_at),
  INDEX idx_audit_user (user_id, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS staff_profiles (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  staff_number VARCHAR(40) NULL UNIQUE,
  job_title VARCHAR(100) NULL,
  department VARCHAR(100) NULL,
  phone VARCHAR(30) NULL,
  joined_on DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS school_profile (
  id TINYINT UNSIGNED PRIMARY KEY,
  school_name VARCHAR(180) NOT NULL,
  school_code VARCHAR(40) NULL,
  school_type VARCHAR(40) NOT NULL DEFAULT 'Government',
  province VARCHAR(60) NULL,
  district VARCHAR(80) NULL,
  address TEXT NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(190) NULL,
  website VARCHAR(190) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO school_profile (id,school_name,school_code,school_type,province) VALUES (1,'Mavambo Secondary School','MVS-001','Government','Harare');

CREATE TABLE IF NOT EXISTS class_subjects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  teacher_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_class_subject (class_id,subject_id),
  CONSTRAINT fk_class_subject_class FOREIGN KEY (class_id) REFERENCES class_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_class_subject_subject FOREIGN KEY (subject_id) REFERENCES subjects(id),
  CONSTRAINT fk_class_subject_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Optional demo learners (remove this block if using a real school's data).
INSERT IGNORE INTO students (admission_no,first_name,last_name,sex,form_level,stream) VALUES
('MVS-2026-001','Nyasha','Mupfumi','F',4,'A'),('MVS-2026-002','Tinashe','Dube','M',6,'Sciences'),('MVS-2026-003','Chipo','Ndlovu','F',3,'B'),('MVS-2026-004','Kudakwashe','Sibanda','M',5,'Arts'),('MVS-2026-005','Ruvimbo','Moyo','F',2,'A'),('MVS-2026-006','Tapiwa','Chirwa','M',1,'C');

INSERT INTO student_enrolments (student_id,academic_year,term_number,form_level,stream,start_date,status)
SELECT id,YEAR(CURDATE()),1,form_level,stream,COALESCE(DATE(created_at),CURDATE()),'Enrolled'
FROM students s
WHERE NOT EXISTS (SELECT 1 FROM student_enrolments e WHERE e.student_id=s.id AND e.end_date IS NULL);

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

CREATE TABLE IF NOT EXISTS guardian_user_links (
  guardian_id BIGINT UNSIGNED PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  linked_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_guardian_user_pair (user_id,guardian_id),
  INDEX idx_guardian_link_user (user_id),
  CONSTRAINT fk_guardian_link_contact FOREIGN KEY (guardian_id) REFERENCES student_guardians(id) ON DELETE CASCADE,
  CONSTRAINT fk_guardian_link_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_guardian_link_admin FOREIGN KEY (linked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS school_inventory_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_code VARCHAR(40) NOT NULL UNIQUE,
  item_name VARCHAR(160) NOT NULL,
  category VARCHAR(80) NOT NULL,
  unit VARCHAR(30) NOT NULL,
  quantity_on_hand DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  reorder_level DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  notes VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_inventory_name (item_name),
  INDEX idx_inventory_category (category,active),
  CONSTRAINT fk_inventory_item_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS school_inventory_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('Receipt','Issue','Write-off','Adjustment') NOT NULL,
  movement_on DATE NOT NULL,
  quantity_delta DECIMAL(12,3) NOT NULL,
  unit_cost DECIMAL(12,2) NULL,
  currency ENUM('USD','ZiG') NULL,
  reference VARCHAR(100) NULL,
  destination VARCHAR(160) NULL,
  reason VARCHAR(255) NOT NULL,
  recorded_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_inventory_movement_item (item_id,movement_on,id),
  INDEX idx_inventory_movement_type (movement_type,movement_on),
  CONSTRAINT fk_inventory_movement_item FOREIGN KEY (item_id) REFERENCES school_inventory_items(id),
  CONSTRAINT fk_inventory_movement_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exam_year SMALLINT UNSIGNED NOT NULL,
  level ENUM('O-Level','A-Level') NOT NULL,
  session_name VARCHAR(80) NOT NULL,
  entry_type VARCHAR(60) NOT NULL,
  centre_number VARCHAR(30) NOT NULL,
  opens_on DATE NOT NULL,
  entry_deadline DATE NOT NULL,
  status ENUM('Draft','Open','Closed') NOT NULL DEFAULT 'Draft',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_exam_session (exam_year,level,session_name,entry_type),
  INDEX idx_exam_open (status,exam_year,level),
  CONSTRAINT fk_exam_session_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_session_subjects (
  session_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  option_group VARCHAR(40) NULL,
  PRIMARY KEY (session_id,subject_id),
  CONSTRAINT fk_exam_offer_session FOREIGN KEY (session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_exam_offer_subject FOREIGN KEY (subject_id) REFERENCES subjects(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_candidates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  surname_snapshot VARCHAR(80) NOT NULL,
  first_names_snapshot VARCHAR(80) NOT NULL,
  sex_snapshot ENUM('F','M') NOT NULL,
  date_of_birth_snapshot DATE NULL,
  national_id_snapshot VARCHAR(30) NULL,
  candidate_type ENUM('Formal','Nonformal') NOT NULL DEFAULT 'Formal',
  payment_type ENUM('Self','BEAM') NOT NULL DEFAULT 'Self',
  nationality ENUM('Local','Territorial') NOT NULL DEFAULT 'Local',
  special_requirements VARCHAR(160) NULL,
  candidate_number VARCHAR(24) NULL,
  status ENUM('Draft','Submitted','Withdrawn') NOT NULL DEFAULT 'Draft',
  withdrawal_reason VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  submitted_by BIGINT UNSIGNED NULL,
  submitted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_exam_candidate (session_id,student_id),
  UNIQUE KEY uq_exam_candidate_number (session_id,candidate_number),
  INDEX idx_exam_candidate_status (session_id,status),
  CONSTRAINT fk_exam_candidate_session FOREIGN KEY (session_id) REFERENCES exam_sessions(id),
  CONSTRAINT fk_exam_candidate_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_exam_candidate_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_exam_candidate_submitter FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_candidate_subjects (
  candidate_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  is_repeat BOOLEAN NOT NULL DEFAULT FALSE,
  previous_year SMALLINT UNSIGNED NULL,
  previous_centre_number VARCHAR(30) NULL,
  previous_candidate_number VARCHAR(24) NULL,
  PRIMARY KEY (candidate_id,subject_id),
  CONSTRAINT fk_exam_candidate_subject_candidate FOREIGN KEY (candidate_id) REFERENCES exam_candidates(id) ON DELETE CASCADE,
  CONSTRAINT fk_exam_candidate_subject_subject FOREIGN KEY (subject_id) REFERENCES subjects(id)
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS boarding_rooms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_name VARCHAR(120) NOT NULL UNIQUE,
  boarding_house VARCHAR(100) NULL,
  gender ENUM('Boys','Girls','Mixed') NOT NULL DEFAULT 'Mixed',
  capacity SMALLINT UNSIGNED NOT NULL,
  house_parent_id BIGINT UNSIGNED NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_boarding_room_active (active,gender),
  CONSTRAINT fk_boarding_house_parent FOREIGN KEY (house_parent_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_boarding_room_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS boarding_allocations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  room_id BIGINT UNSIGNED NOT NULL,
  start_on DATE NOT NULL,
  end_on DATE NULL,
  status ENUM('Active','Completed','Cancelled') NOT NULL DEFAULT 'Active',
  notes VARCHAR(255) NULL,
  assigned_by BIGINT UNSIGNED NULL,
  released_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_boarding_student_dates (student_id,start_on,end_on,status),
  INDEX idx_boarding_room_dates (room_id,start_on,end_on,status),
  CONSTRAINT fk_boarding_allocation_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_boarding_allocation_room FOREIGN KEY (room_id) REFERENCES boarding_rooms(id),
  CONSTRAINT fk_boarding_assigned_user FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_boarding_released_user FOREIGN KEY (released_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email_key CHAR(64) NOT NULL,
  ip_key CHAR(64) NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_email_time (email_key,attempted_at),
  INDEX idx_login_ip_time (ip_key,attempted_at),
  INDEX idx_login_attempted_at (attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transport_vehicles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration VARCHAR(24) NOT NULL UNIQUE,
  make_model VARCHAR(100) NOT NULL,
  seat_capacity SMALLINT UNSIGNED NOT NULL,
  driver_user_id BIGINT UNSIGNED NULL,
  roadworthy_until DATE NULL,
  insurance_until DATE NULL,
  status ENUM('Active','Maintenance','Retired') NOT NULL DEFAULT 'Active',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_transport_driver FOREIGN KEY (driver_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_transport_vehicle_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transport_routes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  route_name VARCHAR(120) NOT NULL UNIQUE,
  route_details TEXT NULL,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  departure_time TIME NULL,
  return_time TIME NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_transport_route_vehicle FOREIGN KEY (vehicle_id) REFERENCES transport_vehicles(id),
  CONSTRAINT fk_transport_route_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transport_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  route_id BIGINT UNSIGNED NOT NULL,
  pickup_point VARCHAR(120) NOT NULL,
  starts_on DATE NOT NULL,
  ends_on DATE NULL,
  status ENUM('Active','Completed') NOT NULL DEFAULT 'Active',
  assigned_by BIGINT UNSIGNED NULL,
  ended_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_transport_assignment_student (student_id,starts_on,ends_on,status),
  INDEX idx_transport_assignment_route (route_id,starts_on,ends_on,status),
  CONSTRAINT fk_transport_assignment_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_transport_assignment_route FOREIGN KEY (route_id) REFERENCES transport_routes(id),
  CONSTRAINT fk_transport_assignment_user FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_transport_ended_user FOREIGN KEY (ended_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transport_register (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  route_id BIGINT UNSIGNED NOT NULL,
  travel_date DATE NOT NULL,
  trip ENUM('Outbound','Return') NOT NULL,
  status ENUM('Boarded','Not On Bus','Authorized Alternative') NOT NULL,
  note VARCHAR(255) NULL,
  recorded_by BIGINT UNSIGNED NULL,
  recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_transport_check (student_id,travel_date,trip),
  INDEX idx_transport_register_route (route_id,travel_date,trip),
  CONSTRAINT fk_transport_register_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_transport_register_route FOREIGN KEY (route_id) REFERENCES transport_routes(id),
  CONSTRAINT fk_transport_register_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS school_activities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  activity_name VARCHAR(140) NOT NULL,
  category ENUM('Sport','Club','Academic','Culture','Service','Other') NOT NULL,
  description VARCHAR(1000) NULL,
  academic_year SMALLINT UNSIGNED NOT NULL,
  patron_id BIGINT UNSIGNED NULL,
  capacity SMALLINT UNSIGNED NULL,
  status ENUM('Active','Closed') NOT NULL DEFAULT 'Active',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_school_activity_year (activity_name,academic_year),
  INDEX idx_school_activity_status (academic_year,status,category),
  CONSTRAINT fk_school_activity_patron FOREIGN KEY (patron_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_school_activity_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_memberships (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  activity_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  joined_on DATE NOT NULL,
  left_on DATE NULL,
  status ENUM('Active','Ended') NOT NULL DEFAULT 'Active',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_membership_activity (activity_id,status,student_id),
  INDEX idx_activity_membership_student (student_id,status,activity_id),
  CONSTRAINT fk_activity_member_activity FOREIGN KEY (activity_id) REFERENCES school_activities(id),
  CONSTRAINT fk_activity_member_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_activity_member_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  activity_id BIGINT UNSIGNED NOT NULL,
  session_type ENUM('Practice','Meeting','Fixture','Competition','Trip','Other') NOT NULL DEFAULT 'Practice',
  title VARCHAR(160) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  venue VARCHAR(160) NULL,
  opponent VARCHAR(140) NULL,
  result VARCHAR(100) NULL,
  notes VARCHAR(500) NULL,
  status ENUM('Scheduled','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_session_date (activity_id,starts_at,status),
  CONSTRAINT fk_activity_session_activity FOREIGN KEY (activity_id) REFERENCES school_activities(id),
  CONSTRAINT fk_activity_session_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_session_attendance (
  session_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  status ENUM('Present','Absent','Excused') NOT NULL,
  note VARCHAR(255) NULL,
  recorded_by BIGINT UNSIGNED NULL,
  recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (session_id,student_id),
  INDEX idx_activity_session_attendance_student (student_id,session_id),
  CONSTRAINT fk_activity_attendance_session FOREIGN KEY (session_id) REFERENCES activity_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_activity_attendance_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_activity_attendance_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

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
