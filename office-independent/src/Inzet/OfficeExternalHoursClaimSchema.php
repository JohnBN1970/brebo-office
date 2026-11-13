<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Additive proposal. Never execute schema changes from a web request.
 * External timesheet evidence is separate from OnSite and payable approvals.
 */
final class OfficeExternalHoursClaimSchema
{
    public static function statements(): array
    {
        return ["CREATE TABLE IF NOT EXISTS office_inzet_external_hours_claims (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            person_id BIGINT UNSIGNED NOT NULL,
            work_date DATE NOT NULL,
            claimed_seconds INT UNSIGNED NOT NULL,
            supplier_reference VARCHAR(128) NOT NULL,
            source_document_sha256 CHAR(64) NOT NULL,
            source_line_reference VARCHAR(128) NOT NULL,
            source_line_sha256 CHAR(64) NOT NULL,
            imported_by VARCHAR(128) NOT NULL,
            imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_external_hours_source_line (source_document_sha256, source_line_reference),
            INDEX idx_external_hours_project_date (project_id, work_date),
            INDEX idx_external_hours_person_date (person_id, work_date),
            CONSTRAINT fk_external_hours_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id),
            CONSTRAINT chk_external_hours_positive CHECK (claimed_seconds > 0 AND claimed_seconds <= 86400)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"];
    }
}
