<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use InvalidArgumentException;

/**
 * Converts a native calculation cost breakdown into the existing immutable
 * CRM work-budget line contract. Amounts are integer cents, never floats.
 * Commercial markups and selling prices are deliberately excluded.
 */
final class OfficeCalcCostCarrierHandoffMapper
{
    private const COST_TYPES = ['labor', 'material', 'equipment', 'subcontract', 'other'];

    public static function map(array $rows): array
    {
        $result = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)
                || !is_string($row['line_id'] ?? null)
                || trim($row['line_id']) === ''
                || !is_int($row['quantity'] ?? null)
                || $row['quantity'] < 0
                || !is_array($row['unit_costs_cents'] ?? null)) {
                throw new InvalidArgumentException('Invalid native calculation row');
            }
            $lineId = $row['line_id'];
            if (isset($seen[$lineId])) {
                throw new InvalidArgumentException('Duplicate native calculation row');
            }
            $seen[$lineId] = true;
            $costs = $row['unit_costs_cents'];
            foreach (array_keys($costs) as $type) {
                if (!in_array($type, self::COST_TYPES, true)) {
                    throw new InvalidArgumentException('Unknown cost carrier');
                }
            }
            foreach (self::COST_TYPES as $type) {
                $unitCost = $costs[$type] ?? 0;
                if (!is_int($unitCost) || $unitCost < 0) {
                    throw new InvalidArgumentException('Invalid unit cost in cents');
                }
                if ($unitCost === 0) {
                    continue;
                }
                if ($row['quantity'] > 0 && $unitCost > intdiv(PHP_INT_MAX, $row['quantity'])) {
                    throw new InvalidArgumentException('Budget amount overflows integer cents');
                }
                $result[] = [
                    'line_id' => $lineId . ':' . $type,
                    'cost_type' => $type,
                    'quantity' => $row['quantity'],
                    'unit_cost_cents' => $unitCost,
                    'activity_ref' => $row['activity_ref'] ?? null,
                ];
            }
        }
        return $result;
    }
}
