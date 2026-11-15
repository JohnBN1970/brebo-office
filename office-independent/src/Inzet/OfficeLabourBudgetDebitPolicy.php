<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Separates verified worked time from planned labour-budget consumption.
 * This is a pure policy; callers must resolve a single authorized shift for
 * the same employee, project and work date and persist exactly once.
 */
final class OfficeLabourBudgetDebitPolicy
{
    /** @return array{approved_seconds:int,budget_debit_seconds:int,variance_seconds:int} */
    public static function calculate(int $approvedSeconds, int $plannedSeconds): array
    {
        if ($approvedSeconds < 0 || $approvedSeconds > 86400
            || $plannedSeconds < 0 || $plannedSeconds > 86400) {
            throw new \InvalidArgumentException('Invalid approved or planned labour seconds');
        }
        if ($approvedSeconds === 0) {
            throw new \DomainException('Unapproved or zero-length time cannot consume a planned shift');
        }
        if ($plannedSeconds === 0) {
            throw new \DomainException('A verified planned shift is required for automatic budget debit');
        }
        return [
            'approved_seconds' => $approvedSeconds,
            'budget_debit_seconds' => $plannedSeconds,
            'variance_seconds' => $approvedSeconds - $plannedSeconds,
        ];
    }
}
