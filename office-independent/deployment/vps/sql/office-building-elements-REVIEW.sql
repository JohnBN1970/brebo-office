-- REVIEW ONLY: verify Office-test schema and backup before applying.
-- Canonical building elements, independent of projects and calculations.
CREATE TABLE IF NOT EXISTS office_building_elements (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 building_id BIGINT UNSIGNED NOT NULL,
 housing_type_id BIGINT UNSIGNED DEFAULT NULL,
 classification_code CHAR(2) NOT NULL,
 element_code VARCHAR(100) NOT NULL,
 description VARCHAR(255) NOT NULL,
 unit VARCHAR(30) NOT NULL,
 quantity DECIMAL(18,4) NOT NULL,
 source_kind VARCHAR(30) NOT NULL,
 source_reference VARCHAR(255) DEFAULT NULL,
 revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY ix_building_classification (building_id, classification_code),
 KEY ix_element_housing (housing_type_id),
 CONSTRAINT fk_element_building FOREIGN KEY (building_id) REFERENCES office_buildings(id),
 CONSTRAINT fk_element_housing FOREIGN KEY (housing_type_id) REFERENCES office_housing_types(id),
 CONSTRAINT ck_element_quantity CHECK (quantity >= 0),
 CONSTRAINT ck_element_source CHECK (source_kind IN ('manual','drawing','measurement'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
