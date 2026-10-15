-- BREBO Office: housing types and scoped partial calculations.
-- REVIEW ONLY. Apply manually to the isolated Office TEST database after backup.
-- No automatic migration, no legacy/Drupal synchronization, no production deployment.
-- A housing type belongs to one building; its building must be linked to the project
-- before a project housing-type allocation is allowed.

CREATE TABLE IF NOT EXISTS office_housing_types (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  building_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(128) NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_housing_type_building_code (building_id, code),
  UNIQUE KEY uq_office_housing_type_id_building (id, building_id),
  CONSTRAINT fk_office_ht_building FOREIGN KEY (building_id)
    REFERENCES office_buildings(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Allocations are project-specific; no dwelling counts are inferred from project titles.
CREATE TABLE IF NOT EXISTS office_project_housing_types (
  project_id BIGINT UNSIGNED NOT NULL,
  building_id BIGINT UNSIGNED NOT NULL,
  housing_type_id BIGINT UNSIGNED NOT NULL,
  dwelling_count INT UNSIGNED NOT NULL,
  PRIMARY KEY (project_id, housing_type_id),
  KEY idx_office_pht_project_building (project_id, building_id),
  CONSTRAINT fk_office_pht_project_building FOREIGN KEY (project_id, building_id)
    REFERENCES office_project_buildings(project_id, building_id) ON DELETE RESTRICT,
  CONSTRAINT fk_office_pht_type_building FOREIGN KEY (housing_type_id, building_id)
    REFERENCES office_housing_types(id, building_id) ON DELETE RESTRICT,
  CONSTRAINT chk_office_pht_count CHECK (dwelling_count > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A calculation scope is metadata only. It does NOT authorize Calc launch.
-- calc_calculation_id remains NULL until an explicit verified mapping exists.
CREATE TABLE IF NOT EXISTS office_project_calculation_scopes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT UNSIGNED NOT NULL,
  building_id BIGINT UNSIGNED NULL,
  housing_type_id BIGINT UNSIGNED NULL,
  scope_kind VARCHAR(24) NOT NULL,
  scope_label VARCHAR(255) NOT NULL,
  calc_calculation_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_office_pcs_project (project_id),
  KEY idx_office_pcs_project_building (project_id, building_id),
  KEY idx_office_pcs_project_housing (project_id, housing_type_id),
  CONSTRAINT fk_office_pcs_project FOREIGN KEY (project_id)
    REFERENCES office_projects(id) ON DELETE RESTRICT,
  CONSTRAINT fk_office_pcs_project_building FOREIGN KEY (project_id, building_id)
    REFERENCES office_project_buildings(project_id, building_id) ON DELETE RESTRICT,
  CONSTRAINT fk_office_pcs_project_housing FOREIGN KEY (project_id, housing_type_id)
    REFERENCES office_project_housing_types(project_id, housing_type_id) ON DELETE RESTRICT,
  CONSTRAINT chk_office_pcs_kind CHECK (scope_kind IN ('project','building','facade','housing_type')),
  CONSTRAINT chk_office_pcs_target CHECK (
    (scope_kind = 'project' AND building_id IS NULL AND housing_type_id IS NULL)
    OR (scope_kind = 'building' AND building_id IS NOT NULL AND housing_type_id IS NULL)
    OR (scope_kind = 'facade' AND building_id IS NOT NULL AND housing_type_id IS NULL)
    OR (scope_kind = 'housing_type' AND building_id IS NOT NULL AND housing_type_id IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
