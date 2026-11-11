-- Additive acceptance proof. Never infer calculation acceptance from won CRM stage.
-- Apply only after schema review and an explicit database installation approval.
CREATE TABLE IF NOT EXISTS office_crm_calculation_acceptances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    opportunity_id BIGINT UNSIGNED NOT NULL,
    calculation_id BIGINT UNSIGNED NOT NULL,
    accepted_revision VARCHAR(128) NOT NULL,
    status VARCHAR(16) NOT NULL,
    accepted_at DATETIME NOT NULL,
    accepted_by BIGINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_crm_calculation_acceptance_opportunity (opportunity_id),
    KEY idx_crm_calculation_acceptance_calculation (calculation_id),
    CONSTRAINT fk_crm_calculation_acceptance_opportunity
        FOREIGN KEY (opportunity_id) REFERENCES office_crm_opportunities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
