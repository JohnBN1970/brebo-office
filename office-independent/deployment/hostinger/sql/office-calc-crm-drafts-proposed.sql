-- PROPOSAL ONLY: CRM-first calculation identity without a premature project.
-- Do not apply until CRM opportunity schema, Calc canonical IDs and grants
-- are reconciled, reviewed and explicitly approved.
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

-- A CRM Calc draft is NOT a row in office_calc_calculations. That existing
-- table requires project_id and is intentionally left unchanged.
-- The existing OfficeCalcLaunchIssuer is project-only and MUST NOT be reused.
-- Access to CRM Calc drafts must use an independent authenticated CRM policy,
-- explicit operator rights, CSRF and a short-lived scoped grant.
-- Canonical Calc source adapter and migration of draft to project Calc are
-- not implemented by this schema proposal.
