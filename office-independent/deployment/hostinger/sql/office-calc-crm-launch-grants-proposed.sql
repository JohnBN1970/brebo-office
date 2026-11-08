-- PROPOSED ONLY: separate, short-lived CRM Calc draft launch grants.
-- Do not apply without review and explicit SQL approval.
CREATE TABLE office_calc_crm_launch_grants (
  reference CHAR(64) NOT NULL,
  session_hash CHAR(64) NOT NULL,
  actor_id BIGINT UNSIGNED NOT NULL,
  draft_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  revoked_at DATETIME NULL,
  PRIMARY KEY (reference),
  KEY idx_calc_crm_grant_draft (draft_id,actor_id),
  CONSTRAINT fk_calc_crm_grant_draft FOREIGN KEY (draft_id)
    REFERENCES office_calc_crm_drafts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- This table is deliberately separate from project-bound session grants.
-- A Calc-side verifier and explicit CSRF-protected POST handoff remain required.
