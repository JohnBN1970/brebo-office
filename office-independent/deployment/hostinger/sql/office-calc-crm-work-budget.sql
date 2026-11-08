-- Office Calc: CRM-first acceptance and immutable execution budget baseline.
-- Schema only; apply in Office-test after verified backup and preflight.
-- A CRM opportunity may create at most one project transfer.
CREATE TABLE IF NOT EXISTS office_calc_crm_acceptance_transfers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    crm_opportunity_id VARCHAR(191) NOT NULL,
    acceptance_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    calculation_id BIGINT UNSIGNED NOT NULL,
    accepted_revision VARCHAR(191) NOT NULL,
    office_project_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_calc_crm_opportunity (crm_opportunity_id),
    UNIQUE KEY uq_calc_acceptance_key (acceptance_key),
    UNIQUE KEY uq_calc_project_transfer (office_project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_calc_work_budget_baselines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    acceptance_transfer_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    locked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_calc_budget_transfer_version (acceptance_transfer_id, version_number),
    CONSTRAINT fk_calc_budget_acceptance FOREIGN KEY (acceptance_transfer_id)
        REFERENCES office_calc_crm_acceptance_transfers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_calc_work_budget_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    baseline_id BIGINT UNSIGNED NOT NULL,
    source_line_id VARCHAR(191) NOT NULL,
    cost_type ENUM('labor','material','equipment','subcontract','other') NOT NULL,
    quantity BIGINT UNSIGNED NOT NULL,
    unit_cost_cents BIGINT UNSIGNED NOT NULL,
    budget_cents BIGINT UNSIGNED NOT NULL,
    activity_ref VARCHAR(191) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_calc_budget_source_line (baseline_id, source_line_id),
    KEY ix_calc_budget_activity (baseline_id, activity_ref),
    CONSTRAINT fk_calc_budget_line_baseline FOREIGN KEY (baseline_id)
        REFERENCES office_calc_work_budget_baselines (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
