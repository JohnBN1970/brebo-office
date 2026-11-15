<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Forward-only additive migration. Existing decisions remain four-eyes.
 * Install only with a guarded CLI after verifying current schema.
 * No automatic migration from HTTP requests.
 */
final class OfficeExternalHoursApprovalModeMigration
{
    public static function statements(): array
    {
        return [
            "ALTER TABLE office_inzet_external_hours_review_decisions
                ADD COLUMN approval_mode ENUM('four_eyes','highest_role_single')
                    NOT NULL DEFAULT 'four_eyes' AFTER approved_payable_seconds",
            "ALTER TABLE office_inzet_external_hours_review_decisions
                MODIFY COLUMN second_reviewer VARCHAR(128) NULL",
            "ALTER TABLE office_inzet_external_hours_review_decisions
                DROP CHECK chk_external_review_distinct",
            "ALTER TABLE office_inzet_external_hours_review_decisions
                ADD CONSTRAINT chk_external_review_mode CHECK (
                    (approval_mode='four_eyes' AND second_reviewer IS NOT NULL
                        AND first_reviewer <> second_reviewer)
                    OR
                    (approval_mode='highest_role_single' AND second_reviewer IS NULL)
                )",
        ];
    }
}
