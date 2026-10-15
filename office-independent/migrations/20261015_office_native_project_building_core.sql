-- BREBO Office native project and building core (MySQL 8 / MariaDB).
-- REVIEW ONLY: apply to the verified isolated Office TEST database first.
-- Do not apply to production, shared Drupal sandbox or deploy workflow automatically.
-- The legacy project numeric ID is preserved explicitly during one-time migration.
-- No trigger, automatic import, external API or Drupal runtime dependency.

CREATE TABLE IF NOT EXISTS office_projects (
  id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(128) NOT NULL,
  title VARCHAR(255) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'concept',
  project_kind VARCHAR(32) NULL,
  description TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_projects_code (code),
  CONSTRAINT chk_office_projects_status CHECK (status IN ('concept','preparation','execution','closeout','closed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_buildings (
  id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(128) NULL,
  name VARCHAR(255) NOT NULL,
  address_line VARCHAR(255) NULL,
  postal_code VARCHAR(32) NULL,
  city VARCHAR(128) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_project_buildings (
  project_id BIGINT UNSIGNED NOT NULL,
  building_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (project_id, building_id),
  KEY idx_office_project_buildings_building (building_id),
  CONSTRAINT fk_office_pb_project FOREIGN KEY (project_id) REFERENCES office_projects(id) ON DELETE RESTRICT,
  CONSTRAINT fk_office_pb_building FOREIGN KEY (building_id) REFERENCES office_buildings(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- General project authorization is distinct from office_calc_project_access.
-- The latter must never grant broad project dossier access.
CREATE TABLE IF NOT EXISTS office_project_memberships (
  project_id BIGINT UNSIGNED NOT NULL,
  operator_id VARCHAR(128) NOT NULL,
  permission VARCHAR(16) NOT NULL,
  PRIMARY KEY (project_id, operator_id),
  KEY idx_office_project_memberships_operator (operator_id),
  CONSTRAINT fk_office_pm_project FOREIGN KEY (project_id) REFERENCES office_projects(id) ON DELETE RESTRICT,
  CONSTRAINT chk_office_pm_permission CHECK (permission IN ('read','manage'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
