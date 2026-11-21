-- PROPOSAL ONLY. NEVER execute automatically in CI or on production.
-- Review existing Office-test schema and backup before an explicit DBA migration.
-- Current native Calc workbench uses this identity; the cost lines stay solely
-- in calculations, calculation_versions and calculation_lines.
CREATE TABLE office_calc_crm_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  crm_revision BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  project_id BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_calc_crm_draft_opportunity (opportunity_id),
  KEY idx_calc_crm_draft_project (project_id),
  CONSTRAINT fk_calc_crm_draft_opportunity FOREIGN KEY (opportunity_id)
    REFERENCES office_crm_opportunities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The current production/test table MAY already exist.
-- No automatic CREATE IF NOT EXISTS: that would mask schema drift.
-- Do not run this proposal without a read-only preflight and explicit review.
-- A project is assigned only through a separate accepted contract.
