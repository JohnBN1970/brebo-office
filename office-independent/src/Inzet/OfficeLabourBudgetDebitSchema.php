<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Explicit migration proposal. Never run DDL from a web request. */
final class OfficeLabourBudgetDebitSchema
{
    /** @return list<string> */
    public static function statements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS office_inzet_labour_budget_debits (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                approved_hours_id BIGINT UNSIGNED NOT NULL,
                shift_id BIGINT UNSIGNED NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                person_id BIGINT UNSIGNED NOT NULL,
                work_date DATE NOT NULL,
                approved_seconds INT UNSIGNED NOT NULL,
                planned_seconds INT UNSIGNED NOT NULL,
                budget_debit_seconds INT UNSIGNED NOT NULL,
                variance_seconds INT NOT NULL,
                source_sha256 CHAR(64) NOT NULL,
                recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_labour_budget_approval (approved_hours_id),
                UNIQUE KEY uq_labour_budget_shift (shift_id),
                INDEX idx_labour_budget_project_date (project_id, work_date),
                CONSTRAINT fk_labour_budget_approval FOREIGN KEY (approved_hours_id) REFERENCES office_inzet_approved_hours(id),
                CONSTRAINT fk_labour_budget_shift FOREIGN KEY (shift_id) REFERENCES office_inzet_shifts(id),
                CONSTRAINT chk_labour_budget_debit_seconds CHECK (budget_debit_seconds <= 86400)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }
}
