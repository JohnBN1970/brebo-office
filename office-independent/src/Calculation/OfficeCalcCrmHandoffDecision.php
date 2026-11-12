<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use InvalidArgumentException;

/**
 * The CRM stage is the source of truth for commercial progress. A calculation
 * is optional; only an explicitly accepted revision may generate a budget.
 */
final class OfficeCalcCrmHandoffDecision
{
    public static function decide(string $crmStage, ?array $calculationAcceptance): string
    {
        if ($crmStage !== 'won') {
            return 'awaiting_crm_acceptance';
        }
        if ($calculationAcceptance === null) {
            return 'direct_assignment_without_budget';
        }
        if (($calculationAcceptance['status'] ?? null) !== 'accepted'
            || !is_int($calculationAcceptance['calculation_id'] ?? null)
            || $calculationAcceptance['calculation_id'] < 1
            || !is_string($calculationAcceptance['accepted_revision'] ?? null)
            || trim($calculationAcceptance['accepted_revision']) === '') {
            throw new InvalidArgumentException('Calculation must have an accepted revision');
        }
        return 'transfer_accepted_calculation';
    }
}
