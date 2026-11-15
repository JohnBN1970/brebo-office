<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Additive, test-scoped reason ledger. Never install during HTTP requests. */
final class OfficeOnSiteRejectionReasonSchema
{
    public static function statements(): array
    {
        return ["CREATE TABLE IF NOT EXISTS office_onsite_rejection_reasons (
            decision_reference VARCHAR(128) NOT NULL PRIMARY KEY,
            reason VARCHAR(1000) NOT NULL,
            recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"];
    }
}
