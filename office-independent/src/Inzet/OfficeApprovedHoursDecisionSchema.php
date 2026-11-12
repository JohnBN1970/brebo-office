<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Explicit migration proposal; never run automatically during requests. */
final class OfficeApprovedHoursDecisionSchema
{
    /** @return list<string> */
    public static function statements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS office_onsite_hours_decisions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                decision_reference VARCHAR(128) NOT NULL,
                in_event_id VARCHAR(128) NOT NULL,
                out_event_id VARCHAR(128) NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                person_id BIGINT UNSIGNED NOT NULL,
                work_date DATE NOT NULL,
                status VARCHAR(16) NOT NULL,
                reviewed_seconds INT UNSIGNED NOT NULL,
                reviewer_id VARCHAR(128) NOT NULL,
                decided_at DATETIME NOT NULL,
                recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_onsite_hours_decision_reference (decision_reference),
                UNIQUE KEY uq_onsite_hours_decision_session (in_event_id,out_event_id),
                INDEX ix_onsite_hours_decision_project (project_id,work_date),
                CONSTRAINT chk_onsite_hours_decision_status CHECK (status IN ('approved','rejected')),
                CONSTRAINT chk_onsite_hours_decision_duration CHECK (reviewed_seconds <= 86400)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }
}
