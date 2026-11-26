-- REVIEW ONLY: Office-test additive schema. Verify parent schema and backup before applying.
-- No project ownership, synthetic dwellings, or automatic deployment.
CREATE TABLE IF NOT EXISTS office_building_dwellings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  building_id BIGINT UNSIGNED NOT NULL,
  housing_type_id BIGINT UNSIGNED DEFAULT NULL,
  dwelling_code VARCHAR(100) NOT NULL,
  house_number VARCHAR(40) NOT NULL,
  floor_label VARCHAR(80) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_building_dwelling_code (building_id, dwelling_code),
  KEY ix_dwelling_housing_type (building_id, housing_type_id),
  CONSTRAINT fk_dwelling_building FOREIGN KEY (building_id) REFERENCES office_buildings(id),
  CONSTRAINT fk_dwelling_housing_type FOREIGN KEY (housing_type_id) REFERENCES office_housing_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Writer must validate housing_type_id belongs to building_id within transaction.
