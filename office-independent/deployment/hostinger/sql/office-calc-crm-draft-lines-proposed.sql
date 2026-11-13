-- PROPOSED ONLY. CRM-first editable Calc lines, independent of project_id.
-- Review and approve schema before any Office-test installation.
CREATE TABLE office_calc_crm_draft_lines (
  draft_id BIGINT UNSIGNED NOT NULL,
  line_id VARCHAR(64) NOT NULL,
  cost_type ENUM('labor','material','equipment','subcontract','other') NOT NULL,
  description VARCHAR(500) NOT NULL,
  quantity BIGINT UNSIGNED NOT NULL,
  unit_cost_cents BIGINT UNSIGNED NOT NULL,
  activity_ref VARCHAR(191) NULL,
  updated_by BIGINT UNSIGNED NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (draft_id,line_id),
  CONSTRAINT fk_calc_crm_line_draft FOREIGN KEY (draft_id)
    REFERENCES office_calc_crm_drafts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
