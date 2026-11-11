<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Explicit migration proposal only. Never execute DDL during a web request.
 * Approved hours are a separate financial/operational ledger, not raw OnSite presence.
 */
final class OfficeApprovedHoursSchema
{
    /** @return list<string> */
    public static function statements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS office_inzet_approved_hours (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                person_id BIGINT UNSIGNED NOT NULL,
                work_date DATE NOT NULL,
                approved_seconds INT UNSIGNED NOT NULL,
                source_kind VARCHAR(32) NOT NULL,
                source_reference VARCHAR(128) NOT NULL,
                source_sha256 CHAR(64) NOT NULL,
                approved_by VARCHAR(128) NOT NULL,
                approved_at DATETIME NOT NULL,
                approval_reference VARCHAR(128) NOT NULL,
                recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_approved_hours_source (source_kind, source_reference),
                INDEX idx_approved_hours_project_date (project_id, work_date),
                INDEX idx_approved_hours_person_date (person_id, work_date),
                CONSTRAINT fk_approved_hours_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id),
                CONSTRAINT chk_approved_hours_seconds CHECK (approved_seconds <= 86400)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }
}
