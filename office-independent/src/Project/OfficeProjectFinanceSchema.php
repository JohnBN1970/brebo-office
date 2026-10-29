<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

/**
 * Project financial agreements.
 *
 * Explicit installation only.
 * Never run automatically from an HTTP request.
 */
final class OfficeProjectFinanceSchema
{
    public static function statements(): array
    {
        return [
            "CREATE TABLE office_project_contracts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                office_project_id BIGINT UNSIGNED NOT NULL,
                contract_number VARCHAR(100) NULL,
                client_name VARCHAR(255) NULL,
                contract_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                status VARCHAR(32) NOT NULL DEFAULT 'draft',
                signed_at DATE NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_project_contract_number
                    (office_project_id, contract_number),
                KEY idx_project_contract_project (office_project_id)
            )",
            "CREATE TABLE office_project_terms (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                contract_id BIGINT UNSIGNED NOT NULL,
                term_number INT UNSIGNED NOT NULL,
                description VARCHAR(255) NOT NULL,
                percentage DECIMAL(7,4) NULL,
                amount DECIMAL(15,2) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'planned',
                due_date DATE NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_contract_term (contract_id, term_number),
                KEY idx_project_term_contract (contract_id)
            )"
        ];
    }
}
