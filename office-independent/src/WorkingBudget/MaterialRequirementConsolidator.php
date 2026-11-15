<?php
declare(strict_types=1);

namespace Brebo\Office\WorkingBudget;

use InvalidArgumentException;
use OverflowException;

/**
 * Consolidates approved baseline material requirements without losing source
 * allocations. Caller supplies canonical article identity and unit, resolved
 * from authoritative material master data. Free descriptions never match.
 * Quantities are integer minor units in the same explicitly supplied scale.
 */
final class MaterialRequirementConsolidator
{
    public static function consolidate(array $requirements): array
    {
        $groups = [];
        $unmatched = [];
        $seen = [];
        foreach ($requirements as $row) {
            if (!is_array($row) || ($row['cost_type'] ?? null) !== 'material') {
                throw new InvalidArgumentException('Expected a material requirement.');
            }
            $source = $row['source_line_id'] ?? null;
            $quantity = $row['quantity'] ?? null;
            $price = $row['unit_cost_cents'] ?? null;
            $activity = $row['activity_ref'] ?? null;
            if ((!is_int($source) && !is_string($source)) || (string)$source === ''
                || isset($seen[(string)$source]) || !is_int($quantity) || $quantity < 0
                || !is_int($price) || $price < 0 || !is_string($activity)) {
                throw new InvalidArgumentException('Invalid or duplicate material allocation.');
            }
            $seen[(string)$source] = true;
            $identity = $row['article_identity'] ?? null;
            $specification = $row['specification'] ?? null;
            $unit = $row['unit'] ?? null;
            $scale = $row['quantity_scale'] ?? null;
            $allocation = [
                'source_line_id' => $source,
                'activity_ref' => $activity,
                'building_id' => $row['building_id'] ?? null,
                'zone_id' => $row['zone_id'] ?? null,
                'quantity' => $quantity,
                'unit_cost_cents' => $price,
            ];
            if (!is_string($identity) || trim($identity) === ''
                || !is_string($specification) || trim($specification) === ''
                || !is_string($unit) || trim($unit) === ''
                || !is_int($scale) || $scale < 1) {
                $unmatched[] = $allocation + ['reason' => 'canonical_identity_or_unit_missing'];
                continue;
            }
            // Length-prefixing avoids separator collisions in external codes.
            $key = '';
            foreach ([$identity, $specification, $unit, (string)$scale] as $part) {
                $key .= strlen($part).':'.$part;
            }
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'article_identity' => $identity,
                    'specification' => $specification,
                    'unit' => $unit,
                    'quantity_scale' => $scale,
                    'budget_quantity' => 0,
                    'allocations' => [],
                    'ordered_quantity' => null,
                    'received_quantity' => null,
                    'remaining_to_order' => null,
                ];
            }
            if ($quantity > PHP_INT_MAX - $groups[$key]['budget_quantity']) {
                throw new OverflowException('Material quantity overflow.');
            }
            $groups[$key]['budget_quantity'] += $quantity;
            $groups[$key]['allocations'][] = $allocation;
        }
        return ['groups' => array_values($groups), 'unmatched' => $unmatched];
    }
}
