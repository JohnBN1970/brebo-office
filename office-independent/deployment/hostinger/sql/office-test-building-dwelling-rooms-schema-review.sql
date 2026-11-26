-- REVIEW ONLY: verify parent schema and backup before installation.
CREATE TABLE IF NOT EXISTS office_building_dwelling_rooms (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dwelling_id BIGINT UNSIGNED NOT NULL,
  room_code VARCHAR(100) NOT NULL,
  room_name VARCHAR(160) NOT NULL,
  floor_label VARCHAR(80) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dwelling_room_code (dwelling_id, room_code),
  CONSTRAINT fk_room_dwelling FOREIGN KEY (dwelling_id) REFERENCES office_building_dwellings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
