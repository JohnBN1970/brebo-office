<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/**
 * Explicit CRM-first entrypoint for an accepted calculation.
 * The project callback must create the Office project using the supplied PDO
 * connection, inside the transfer service transaction.
 */
final class OfficeCalcAcceptedOpportunityTransfer
{
    public static function execute(PDO $db, array $acceptance, array $calculationLines, callable $createProject): array
    {
        require_once __DIR__ . '/OfficeCalcCrmTransferService.php';
        require_once __DIR__ . '/OfficeCalcPersistedCrmAcceptance.php';

        return (new OfficeCalcCrmTransferService($db))->transfer(
            $acceptance,
            $calculationLines,
            $createProject,
            static fn(PDO $transaction, array $candidate): bool =>
                OfficeCalcPersistedCrmAcceptance::verify($transaction, $candidate)
        );
    }
}
