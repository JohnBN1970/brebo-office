CREATE TABLE IF NOT EXISTS office_quotation_budget_bindings (
 quotation_id BIGINT UNSIGNED NOT NULL,
 quotation_version INT UNSIGNED NOT NULL,
 opportunity_id BIGINT UNSIGNED NOT NULL,
 draft_id BIGINT UNSIGNED NOT NULL,
 calculation_version INT UNSIGNED NOT NULL,
 source_hash CHAR(64) NOT NULL,
 cost_json LONGTEXT NOT NULL,
 cost_sha256 CHAR(64) NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(quotation_id,quotation_version),
 CONSTRAINT fk_quotation_budget_quote FOREIGN KEY(quotation_id) REFERENCES office_quotations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
