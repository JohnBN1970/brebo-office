-- BREBO Office-test unified CRM-first Calc contract.
-- REVIEW ONLY. Apply only to u213420663_office_test after verifying
-- office_crm_opportunities(id BIGINT UNSIGNED) and the canonical Calc tables.
-- No data copy, no historical Calc import, no Drupal, no second Calc line store.
--
-- Canonical Calc rows: calculations, calculation_versions, calculation_lines.
-- Office owns: CRM opportunity, draft identity, authorization and acceptance.
-- The already-proposed office_calc_crm_draft_lines table MUST NOT be installed:
-- it would create a competing source of truth for calculation rows.
--
-- After installation, validate real CRM revision/role access and same-origin
-- application routing before enabling any feature switches.
CREATE TABLE IF NOT EXISTS office_calc_crm_drafts (
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
    REFERENCES office_crm_opportunities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_calc_crm_launch_grants (
  reference CHAR(64) NOT NULL,
  session_hash CHAR(64) NOT NULL,
  actor_id BIGINT UNSIGNED NOT NULL,
  draft_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  revoked_at DATETIME NULL,
  PRIMARY KEY (reference),
  KEY idx_calc_crm_grant_draft (draft_id, actor_id),
  CONSTRAINT fk_calc_crm_grant_draft FOREIGN KEY (draft_id)
    REFERENCES office_calc_crm_drafts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project-free mapping: calculations.office_calculation_id =
-- office_calc_crm_drafts.id, calculations.office_project_id = NULL until acceptance.
-- Do not write calculation_lines to office_calc_crm_draft_lines.
-- The original Calc project data u213420663_calc is out of scope.
