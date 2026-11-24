USE mavambo_school;

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
