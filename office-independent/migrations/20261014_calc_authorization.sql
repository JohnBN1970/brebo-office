-- Additive, not applied automatically. Review against production backup first.
-- Existing office_operator_accounts.id must match VARCHAR(128) identifiers.
CREATE TABLE IF NOT EXISTS office_calculation_permissions (
  operator_id VARCHAR(128) NOT NULL PRIMARY KEY,
  can_calculate TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_calc_permission_operator FOREIGN KEY (operator_id)
    REFERENCES office_operator_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_calculation_registry (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_operator_id VARCHAR(128) NOT NULL,
  project_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_calc_registry_owner FOREIGN KEY (owner_operator_id)
    REFERENCES office_operator_accounts(id),
  KEY idx_calc_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Project identifiers are Office-independent references for the first phase.
-- No project table was found in the verified standalone Office database.
CREATE TABLE IF NOT EXISTS office_calculation_project_access (
  operator_id VARCHAR(128) NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  can_calculate TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (operator_id, project_id),
  CONSTRAINT fk_calc_project_operator FOREIGN KEY (operator_id)
    REFERENCES office_operator_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
