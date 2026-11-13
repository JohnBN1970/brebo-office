-- PROPOSED ONLY. Do not deploy without approval, backup and schema reconciliation.
-- A won opportunity is NOT itself proof of accepted Calc revision.
-- InnoDB permits row locks in the same PDO transaction as the handoff.
CREATE TABLE office_crm_calc_acceptances (
  opportunity_id BIGINT UNSIGNED NOT NULL,
  calculation_id BIGINT UNSIGNED NOT NULL,
  accepted_revision VARCHAR(191) NOT NULL,
  status VARCHAR(16) NOT NULL,
  accepted_by BIGINT UNSIGNED NOT NULL,
  accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (opportunity_id),
  CONSTRAINT fk_crm_calc_acceptance_opportunity FOREIGN KEY (opportunity_id)
    REFERENCES office_crm_opportunities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE office_crm_calc_accepted_lines (
  opportunity_id BIGINT UNSIGNED NOT NULL,
  source_line_id VARCHAR(191) NOT NULL,
  cost_type ENUM('labor','material','equipment','subcontract','other') NOT NULL,
  quantity BIGINT UNSIGNED NOT NULL,
  unit_cost_cents BIGINT UNSIGNED NOT NULL,
  activity_ref VARCHAR(191) NULL,
  PRIMARY KEY (opportunity_id,source_line_id),
  CONSTRAINT fk_crm_calc_accepted_lines_opportunity FOREIGN KEY (opportunity_id)
    REFERENCES office_crm_calc_acceptances (opportunity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- IMPORTANT: acceptance writer is not implemented. It must snapshot the
-- canonical Calc source revision and lines transactionally with CRM acceptance,
-- verify the actor and prevent subsequent changes. No backfill is permitted.
