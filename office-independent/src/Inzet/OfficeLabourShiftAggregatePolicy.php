<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Pure, side-effect-free calculation for multiple verified OnSite approvals
 * belonging to ONE shift. Never call with unverified user-submitted durations.
 *
 * The persisted debit schema currently supports one approval per shift;
 * this policy does not authorize switching on the runtime feature flag.
 */
final class OfficeLabourShiftAggregatePolicy
{
    /**
     * @param list<array{approval_id:int,approved_seconds:int}> $approvals
     * @return array{approved_seconds:int,budget_debit_seconds:int,variance_seconds:int,approval_count:int}
     */
    public static function calculate(int $plannedSeconds, array $approvals): array
    {
        if ($plannedSeconds < 1 || $plannedSeconds > 86400) {
            throw new \InvalidArgumentException('Invalid planned shift duration');
        }
        if ($approvals === []) {
            throw new \DomainException('At least one verified approval is required');
        }
        $total = 0;
        $seen = [];
        foreach ($approvals as $approval) {
            if (!is_array($approval) || !isset($approval['approval_id'], $approval['approved_seconds'])
                || !is_int($approval['approval_id']) || $approval['approval_id'] < 1
                || !is_int($approval['approved_seconds']) || $approval['approved_seconds'] < 1
                || $approval['approved_seconds'] > 86400
                || isset($seen[$approval['approval_id']])) {
                throw new \DomainException('Invalid or repeated approval');
            }
            $seen[$approval['approval_id']] = true;
            $total += $approval['approved_seconds'];
            if ($total > 86400) {
                throw new \DomainException('Aggregate approved time exceeds one day');
            }
        }
        return [
            'approved_seconds' => $total,
            'budget_debit_seconds' => $plannedSeconds,
            'variance_seconds' => $total - $plannedSeconds,
            'approval_count' => count($seen),
        ];
    }
}
