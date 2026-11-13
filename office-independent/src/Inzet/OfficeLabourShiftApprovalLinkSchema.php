<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Proposed additive migration for a many-approvals-to-one-shift audit ledger.
 * Migration is deliberately NOT executed by a web request or CI.
 */
final class OfficeLabourShiftApprovalLinkSchema
{
    /** @return list<string> */
    public static function statements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS office_inzet_labour_shift_approval_links (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                shift_id BIGINT UNSIGNED NOT NULL,
                approved_hours_id BIGINT UNSIGNED NOT NULL,
                approved_seconds INT UNSIGNED NOT NULL,
                source_sha256 CHAR(64) NOT NULL,
                recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_labour_shift_approval (approved_hours_id),
                INDEX idx_labour_shift_approvals (shift_id, approved_hours_id),
                CONSTRAINT fk_labour_shift_link_shift FOREIGN KEY (shift_id)
                    REFERENCES office_inzet_shifts(id),
                CONSTRAINT fk_labour_shift_link_approved FOREIGN KEY (approved_hours_id)
                    REFERENCES office_inzet_approved_hours(id),
                CONSTRAINT chk_labour_shift_link_positive CHECK (approved_seconds > 0 AND approved_seconds <= 86400)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }
}
