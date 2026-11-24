USE mavambo_school;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email_key CHAR(64) NOT NULL,
  ip_key CHAR(64) NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_email_time (email_key,attempted_at),
  INDEX idx_login_ip_time (ip_key,attempted_at),
  INDEX idx_login_attempted_at (attempted_at)
) ENGINE=InnoDB;
