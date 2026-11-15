<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

/** Proposed schema; install through reviewed migration only, never a web request. */
final class OfficeApprovedLabourCostSchema
{
    public static function statements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS office_inzet_actual_labour_costs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                approved_hours_id BIGINT UNSIGNED NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                person_id BIGINT UNSIGNED NOT NULL,
                work_date DATE NOT NULL,
                approved_seconds INT UNSIGNED NOT NULL,
                hourly_rate_cents BIGINT UNSIGNED NOT NULL,
                amount_cents BIGINT UNSIGNED NOT NULL,
                source_sha256 CHAR(64) NOT NULL,
                recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_actual_labour_approval (approved_hours_id),
                INDEX idx_actual_labour_project_date (project_id,work_date),
                CONSTRAINT fk_actual_labour_approval FOREIGN KEY (approved_hours_id) REFERENCES office_inzet_approved_hours(id),
                CONSTRAINT chk_actual_labour_positive CHECK (amount_cents > 0 AND hourly_rate_cents > 0 AND approved_seconds > 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_inzet_actual_labour_rates (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                person_id BIGINT UNSIGNED NOT NULL,
                valid_from DATE NOT NULL,
                valid_until DATE NULL,
                hourly_rate_cents BIGINT UNSIGNED NOT NULL,
                authorized_by VARCHAR(128) NOT NULL,
                authorized_at DATETIME NOT NULL,
                revoked_at DATETIME NULL,
                INDEX idx_actual_rate_context (project_id,person_id,valid_from),
                CONSTRAINT chk_actual_rate_positive CHECK (hourly_rate_cents > 0),
                CONSTRAINT chk_actual_rate_dates CHECK (valid_until IS NULL OR valid_until >= valid_from)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }
}
