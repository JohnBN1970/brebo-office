-- BREBO Office-test / shared canonical CRM + project Calc schema.
-- REVIEW ONLY. DO NOT EXECUTE without explicit DB migration approval.
-- The OLD office-crm-calc-projectless-bootstrap-REVIEW.sql is BLOCKED.
-- This is an alternative schema proposal, NOT a deployment.
--
-- Read-only VPS inventory 2026-11-23 confirmed:
--   brebo_office_test, office_crm_opportunities.id BIGINT UNSIGNED,
--   office_operator_accounts.id BIGINT UNSIGNED; two closed Deals;
--   no acceptance transfers or work-budget lines.
-- Backup restore smoke test passed. Revalidate immediately before any installation.
-- Verified read-only VPS preflight (2026-11-23): both Office identity/access tables,
-- four CRM Calc storage tables and CRM revision are all MISSING.
-- This remains a proposal; revalidate on the chosen connection before applying.
-- CRM revision ALTER is a SEPARATE reviewed migration.
--
-- Identity:
--   office_calc_calculations.id is the ONLY Office calculation identifier.
--   Deal draft ID is separate, linked by UNIQUE calculation_id.
--   calculations.office_calculation_id refers to Office identity, not draft ID.
--   All Calc lines live in calculation_versions + calculation_lines.
--   Project grants are separate and remain fail-closed for projectless rows.
--
CREATE TABLE office_calc_calculations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  UNIQUE KEY uq_office_calc_id_project(id, project_id),
  KEY idx_office_calc_project(project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Project calculation grants use the SAME Office identity but are never
-- inherited by a projectless Deal. No implicit permissions or seed rows.
-- Existing project launch additionally checks native project-manage permission.
CREATE TABLE office_calc_project_access (
  calculation_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  operator_id BIGINT UNSIGNED NOT NULL,
  permission ENUM('read','manage') NOT NULL DEFAULT 'read',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (calculation_id,project_id,operator_id),
  KEY idx_office_calc_access_operator (operator_id,project_id),
  CONSTRAINT fk_office_calc_access_calculation_project
    FOREIGN KEY (calculation_id,project_id)
    REFERENCES office_calc_calculations (id,project_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE office_calc_crm_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  crm_revision BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NULL,
  calculation_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  UNIQUE KEY uq_calc_draft_opportunity(opportunity_id),
  UNIQUE KEY uq_calc_draft_calculation(calculation_id),
  KEY idx_calc_draft_creator(created_by),
  CONSTRAINT fk_calc_draft_opportunity FOREIGN KEY (opportunity_id)
    REFERENCES office_crm_opportunities(id),
  CONSTRAINT fk_calc_draft_calculation FOREIGN KEY (calculation_id)
    REFERENCES office_calc_calculations(id),
  CONSTRAINT fk_calc_draft_creator FOREIGN KEY (created_by)
    REFERENCES office_operator_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE calculations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  office_project_id BIGINT UNSIGNED NULL,
  office_calculation_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(191) NOT NULL,
  title VARCHAR(255) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  UNIQUE KEY uq_calc_office_calculation_id(office_calculation_id),
  UNIQUE KEY uq_calc_code(code),
  CONSTRAINT fk_calc_canonical_office_identity FOREIGN KEY (office_calculation_id)
    REFERENCES office_calc_calculations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE calculation_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  calculation_id BIGINT UNSIGNED NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  direct_cost DECIMAL(22,8) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  UNIQUE KEY uq_calc_version_number(calculation_id,version_no),
  CONSTRAINT fk_calc_version FOREIGN KEY (calculation_id)
    REFERENCES calculations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE calculation_lines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  version_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  sort_order INT UNSIGNED NOT NULL,
  line_type VARCHAR(24) NOT NULL,
  code VARCHAR(191) NOT NULL,
  description VARCHAR(500) NOT NULL,
  unit VARCHAR(32) NULL,
  quantity DECIMAL(18,4) NOT NULL,
  labour_unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  material_unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  equipment_unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  subcontracting_unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  other_unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  PRIMARY KEY(id),
  UNIQUE KEY uq_calc_line_version_code(version_id,code),
  KEY idx_calc_line_order(version_id,sort_order,id),
  CONSTRAINT fk_calc_line_version FOREIGN KEY (version_id)
    REFERENCES calculation_versions(id),
  CONSTRAINT fk_calc_line_parent FOREIGN KEY (parent_id)
    REFERENCES calculation_lines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- No DDL for existing acceptance transfers / locked work budgets.
-- No implicit grants, backfills, flags, project creation or production changes.
-- MySQL DDL is NOT transactional. Require restore plan and E2E evidence.
