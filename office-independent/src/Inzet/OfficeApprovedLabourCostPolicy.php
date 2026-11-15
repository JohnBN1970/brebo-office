<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Pure EUR cost valuation of independently verified approved time.
 * Caller must resolve an authorized, effective-dated rate for the same
 * employee, project and work date; never use a client-supplied rate.
 * No posting or database mutation is performed here.
 */
final class OfficeApprovedLabourCostPolicy
{
    public static function calculate(int $approvedSeconds, ?int $hourlyRateCents): int
    {
        if ($approvedSeconds < 1 || $approvedSeconds > 86400) {
            throw new \DomainException('Verified positive approved time required');
        }
        if ($hourlyRateCents === null || $hourlyRateCents < 1) {
            throw new \DomainException('Verified positive labour rate required');
        }
        if ($hourlyRateCents > intdiv(PHP_INT_MAX, $approvedSeconds)) {
            throw new \OverflowException('Labour cost overflow');
        }
        // Integer half-up rounding of cents; no floating-point money arithmetic.
        $numerator = $approvedSeconds * $hourlyRateCents;
        $cost = intdiv($numerator, 3600);
        if ($numerator % 3600 >= 1800) ++$cost;
        return $cost;
    }
}
