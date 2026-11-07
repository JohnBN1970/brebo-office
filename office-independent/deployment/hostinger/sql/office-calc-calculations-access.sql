-- BREBO Office-test only. REVIEW BEFORE APPLYING TO STAGING.
-- Office owns the canonical project -> calculation identity and explicit operator access.
-- Calc's calculations.office_calculation_id references office_calc_calculations.id;
-- do not confuse Calc's local calculations.id with the Office calculation identifier.
-- Do not run on production or migrate legacy Calc records implicitly.
CREATE TABLE IF NOT EXISTS office_calc_calculations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_calc_id_project (id, project_id),
  KEY idx_office_calc_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- No blanket default permissions. Each permitted operator must receive an
-- explicit row after Office's project manage permission has been verified.
CREATE TABLE IF NOT EXISTS office_calc_project_access (
  calculation_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  operator_id BIGINT UNSIGNED NOT NULL,
  permission ENUM('read','manage') NOT NULL DEFAULT 'read',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (calculation_id, project_id, operator_id),
  KEY idx_office_calc_access_operator (operator_id, project_id),
  CONSTRAINT fk_office_calc_access_calculation_project
    FOREIGN KEY (calculation_id, project_id)
    REFERENCES office_calc_calculations (id, project_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- No automatic backfill, seed users, or grants. Existing Office membership alone
-- is insufficient to launch a calculation. An authorized provisioning flow is
-- required before any calculation becomes accessible.
