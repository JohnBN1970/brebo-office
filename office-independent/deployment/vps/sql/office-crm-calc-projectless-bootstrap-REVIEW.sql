-- BREBO Office-test CRM -> Calc canonical draft bootstrap (REVIEW ONLY).
-- NOT AN AUTOMATIC MIGRATION. Never apply to production or to an unverified schema.
-- Prerequisites: backup, isolated DB identity = brebo_office_test, zero existing target
-- tables, verified canonical ownership, reviewed CRM ID types, and release approval.
-- The preflight currently reports MISSING: these definitions are proposed, not installed.
-- Application flags remain disabled; no seed data, grants or project creation.
--
-- Install order after review:
-- (1) CRM Deal revision added by a separately reviewed ALTER TABLE migration
-- (2) these four new tables in this order
-- (3) information_schema audit and actual MySQL integration tests
-- Do not use CREATE TABLE IF NOT EXISTS as proof that installed schemas match.
--
-- Critical contract: office_calc_crm_drafts is only CRM identity/access metadata.
-- All line financial truth is in calculations -> calculation_versions -> calculation_lines.
-- Existing office_calc_crm_acceptance_transfers and work-budget tables are untouched.

CREATE TABLE office_calc_crm_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  crm_revision BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_calc_draft_opportunity (opportunity_id),
  KEY ix_office_calc_draft_created_by (created_by),
  CONSTRAINT fk_office_calc_draft_opportunity
    FOREIGN KEY (opportunity_id) REFERENCES office_crm_opportunities(id),
  CONSTRAINT fk_office_calc_draft_creator
    FOREIGN KEY (created_by) REFERENCES office_operator_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE calculations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  office_project_id BIGINT UNSIGNED NULL,
  office_calculation_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(191) NOT NULL,
  title VARCHAR(255) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_calc_office_id (office_calculation_id),
  UNIQUE KEY uq_calc_code (code),
  CONSTRAINT fk_calc_draft_identity FOREIGN KEY (office_calculation_id)
    REFERENCES office_calc_crm_drafts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE calculation_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  calculation_id BIGINT UNSIGNED NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  direct_cost DECIMAL(22,8) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_calc_version (calculation_id,version_no),
  CONSTRAINT fk_calc_version_calculation FOREIGN KEY (calculation_id)
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
  PRIMARY KEY (id),
  UNIQUE KEY uq_calc_line_code (version_id,code),
  KEY ix_calc_lines_order (version_id,sort_order,id),
  CONSTRAINT fk_calc_line_version FOREIGN KEY (version_id)
    REFERENCES calculation_versions(id),
  CONSTRAINT fk_calc_line_parent FOREIGN KEY (parent_id)
    REFERENCES calculation_lines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Deliberately not included: revision ALTER (requires DB type/data confirmation),
-- collaboration/history/material/quantity extensions (separate flag-gated schemas),
-- and decimal work-budget columns (requires review of locked baseline contract).
-- No COMMIT or flag activation: this file is reviewed SQL, never auto-run.
