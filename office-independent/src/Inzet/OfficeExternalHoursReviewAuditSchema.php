<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Additive append-only review audit proposal.
 * Install via separately guarded Office-test CLI only, never from a web request.
 * References the immutable external claim, not the OnSite presence ledger.
 */
final class OfficeExternalHoursReviewAuditSchema
{
    public static function statements(): array
    {
        return ["CREATE TABLE IF NOT EXISTS office_inzet_external_hours_review_decisions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            external_claim_id BIGINT UNSIGNED NOT NULL,
            decision_reference CHAR(64) NOT NULL,
            source_line_sha256 CHAR(64) NOT NULL,
            present_seconds INT UNSIGNED NOT NULL,
            invoiced_seconds INT UNSIGNED NULL,
            approved_payable_seconds INT UNSIGNED NOT NULL,
            first_reviewer VARCHAR(128) NOT NULL,
            second_reviewer VARCHAR(128) NOT NULL,
            reason TEXT NOT NULL,
            decided_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_external_review_decision_reference (decision_reference),
            INDEX idx_external_review_claim (external_claim_id,decided_at),
            CONSTRAINT fk_external_review_claim FOREIGN KEY (external_claim_id)
                REFERENCES office_inzet_external_hours_claims(id),
            CONSTRAINT chk_external_review_present CHECK (present_seconds <= 86400),
            CONSTRAINT chk_external_review_invoiced CHECK (invoiced_seconds IS NULL OR invoiced_seconds <= 86400),
            CONSTRAINT chk_external_review_payable CHECK (approved_payable_seconds <= 86400),
            CONSTRAINT chk_external_review_distinct CHECK (first_reviewer <> second_reviewer)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"];
    }
}
