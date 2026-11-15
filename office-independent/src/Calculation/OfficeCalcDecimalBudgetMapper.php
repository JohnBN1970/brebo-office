<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;

/**
 * Decimal quantities and prices for NEW baselines only. Never converts existing
 * locked legacy lines. The legacy integer columns remain compatible, holding
 * quantity=1 and the rounded-to-cent line total for v2 lines.
 */
final class OfficeCalcDecimalBudgetMapper
{
    public static function map(string $sourceLineId, string $quantity, array $unitCosts, ?string $activityRef = null): array
    {
        require_once __DIR__.'/OfficeCalcCostBreakdown.php';
        if ($sourceLineId === '' || strlen($sourceLineId) > 116) {
            throw new DomainException('Ongeldige bronregel.');
        }
        if (!preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,4})?$/D', $quantity) || bccomp($quantity, '0', 4) <= 0) {
            throw new DomainException('Hoeveelheid moet positief zijn met maximaal vier decimalen.');
        }
        $calculated = OfficeCalcCostBreakdown::calculate($quantity, $unitCosts);
        $items = [];
        foreach (OfficeCalcCostBreakdown::TYPES as $type) {
            $unit = $calculated['columns'][$type]['unit_cost'];
            if (bccomp($unit, '0', 4) === 0) continue;
            $total = bcmul($quantity, $unit, 8);
            // Round half-up once, at the extended line total, never at unit level.
            $roundedCents = bcadd(bcmul($total, '100', 8), '0.5', 0);
            if (bccomp($roundedCents, (string)PHP_INT_MAX, 0) > 0) {
                throw new DomainException('Regelbedrag overschrijdt integerbereik.');
            }
            $items[] = [
                'line_id' => $sourceLineId.':'.$type,
                'cost_type' => $type,
                'quantity' => 1,
                'unit_cost_cents' => (int)$roundedCents,
                'activity_ref' => $activityRef,
                'quantity_decimal' => bcadd($quantity, '0', 4),
                'unit_cost_decimal' => bcadd($unit, '0', 4),
                'budget_decimal' => bcadd($total, '0', 4),
            ];
        }
        if ($items === []) throw new DomainException('Lege kostenregel kan niet worden overgedragen.');
        return $items;
    }
}
