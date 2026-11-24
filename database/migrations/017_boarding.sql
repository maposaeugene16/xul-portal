USE mavambo_school;

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
