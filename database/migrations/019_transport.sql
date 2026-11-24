USE mavambo_school;

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
