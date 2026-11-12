<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use DomainException;
use PDO;

/**
 * One entrypoint for an explicitly accepted CRM calculation and a native
 * Office project. The transfer service owns the single database transaction.
 * No automatic CRM stage change or synthetic calculation acceptance.
 */
final class OfficeCalcNativeProjectHandoff
{
    public static function execute(
        PDO $db,
        array $acceptance,
        array $calculationLines,
        array $project
    ): array {
        require_once __DIR__ . '/OfficeCalcAcceptedOpportunityTransfer.php';
        require_once __DIR__ . '/OfficeNativeProjectFactory.php';

        $opportunity = $acceptance['crm_opportunity_id'] ?? null;
        if (!is_string($opportunity) || !ctype_digit($opportunity)
            || (int)$opportunity < 1 || !is_int($project['operator_id'] ?? null)
            || $project['operator_id'] < 1) {
            throw new DomainException('CRM acceptance and native project owner required');
        }
        // Caller supplies the verified project title/code and operator identity.
        // Do not infer either from untrusted browser input.
        require_once dirname(__DIR__) . '/Crm/OfficeCrmFunnel.php';
        $expectedStage = $project['expected_crm_stage'] ?? null;
        if (!is_string($expectedStage) || !\\Brebo\\Office\\Crm\\OfficeCrmFunnel::canTransition($expectedStage, 'won')) {
            throw new DomainException('Expected CRM stage required for atomic acceptance');
        }
        return OfficeCalcAcceptedOpportunityTransfer::execute(
            $db,
            $acceptance,
            $calculationLines,
            static fn(PDO $transaction, array $accepted): int =>
                OfficeNativeProjectFactory::create($transaction, $project),
            static function (PDO $transaction, array $accepted) use ($expectedStage): bool {
                $stmt = $transaction->prepare(
                    "UPDATE office_crm_opportunities SET stage='won', status='closed' " .
                    "WHERE id=? AND stage=? AND status='open'"
                );
                $stmt->execute([(int)$accepted['crm_opportunity_id'], $expectedStage]);
                return $stmt->rowCount() === 1;
            }
        );
    }
}
