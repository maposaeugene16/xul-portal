USE mavambo_school;

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
