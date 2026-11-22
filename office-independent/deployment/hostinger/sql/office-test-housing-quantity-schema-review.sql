-- BREBO Office-test: additive housing type and quantity source schema.
-- REVIEW ONLY: do not apply until verified against live database and backup.
-- No seed data, no changes to existing tables, no Calc prices transferred.
CREATE TABLE IF NOT EXISTS office_housing_types (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  building_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(100) NOT NULL,
  name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_housing_building_code (building_id, code),
  KEY ix_housing_building (building_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_project_housing_types (
  project_id BIGINT UNSIGNED NOT NULL,
  building_id BIGINT UNSIGNED NOT NULL,
  housing_type_id BIGINT UNSIGNED NOT NULL,
  dwelling_count INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (project_id, building_id, housing_type_id),
  KEY ix_project_housing_type (housing_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_project_calculation_scopes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  building_id BIGINT UNSIGNED DEFAULT NULL,
  housing_type_id BIGINT UNSIGNED DEFAULT NULL,
  scope_kind VARCHAR(40) NOT NULL,
  scope_label VARCHAR(255) NOT NULL,
  calc_calculation_id BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_scope_project (project_id, scope_kind),
  KEY ix_scope_housing (building_id, housing_type_id),
  KEY ix_scope_calc (calc_calculation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_scope_quantity_lines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  scope_id BIGINT UNSIGNED NOT NULL,
  line_code VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  unit VARCHAR(30) NOT NULL,
  quantity_per_dwelling DECIMAL(18,4) NOT NULL,
  unit_price_ex_vat DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_scope_line (scope_id, line_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
