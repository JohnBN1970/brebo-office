<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;

/**
 * Convert a five-column cost grid to the existing immutable work-budget
 * handoff contract. Fail closed: the current handoff only accepts whole
 * quantities and integer cents. Never silently round fractional quantities.
 */
final class OfficeCalcBudgetLineBridge
{
    public static function map(string $sourceLineId, string $quantity, array $unitCosts, ?string $activityRef = null): array
    {
        require_once __DIR__.'/OfficeCalcCostBreakdown.php';
        if ($sourceLineId === '' || strlen($sourceLineId) > 128) {
            throw new DomainException('Ongeldige bronregel.');
        }
        if (!preg_match('/^[1-9][0-9]{0,7}$/D', $quantity)) {
            throw new DomainException('Werkbegroting vereist gehele hoeveelheden; decimale hoeveelheden moeten eerst contractueel ondersteund worden.');
        }
        $calculated = OfficeCalcCostBreakdown::calculate($quantity, $unitCosts);
        $items = [];
        foreach (OfficeCalcCostBreakdown::TYPES as $type) {
            $unit = $calculated['columns'][$type]['unit_cost'];
            if (bccomp($unit, '0', 4) === 0) {
                continue;
            }
            // Existing handoff persists cents, so fractions of a cent cannot
            // be transferred without explicit commercial rounding approval.
            if (bccomp($unit, bcadd($unit, '0', 2), 4) !== 0) {
                throw new DomainException('Eenheidsprijs bevat fracties van een cent.');
            }
            $cents = bcmul($unit, '100', 0);
            if (bccomp($cents, (string)PHP_INT_MAX, 0) > 0) {
                throw new DomainException('Eenheidsprijs overschrijdt integerbereik.');
            }
            $items[] = [
                'line_id' => $sourceLineId.':'.$type,
                'cost_type' => $type,
                'quantity' => (int)$quantity,
                'unit_cost_cents' => (int)$cents,
                'activity_ref' => $activityRef,
            ];
        }
        if ($items === []) {
            throw new DomainException('Lege kostenregel kan niet worden overgedragen.');
        }
        return $items;
    }
}
