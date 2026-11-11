<?php
declare(strict_types=1);

namespace Brebo\Office\WorkingBudget;

use InvalidArgumentException;

/**
 * Deterministic, read-only material consolidation. No fuzzy name matching:
 * only a verified article code + specification + unit can be grouped.
 * Quantities are integer thousandths to avoid floating point drift.
 */
final class MaterialConsolidation
{
    public static function group(array $lines): array
    {
        $groups = [];
        $unmatched = [];
        $seen = [];
        foreach ($lines as $line) {
            if (!is_array($line)) throw new InvalidArgumentException('Invalid material line.');
            $source = $line['source_line_id'] ?? null;
            $code = $line['article_code'] ?? null;
            $spec = $line['specification'] ?? null;
            $unit = $line['unit'] ?? null;
            $quantity = $line['quantity_milli'] ?? null;
            $cents = $line['budget_cents'] ?? null;
            if (!is_string($source) || $source === '' || isset($seen[$source])
                || !is_int($quantity) || $quantity < 0
                || !is_int($cents) || $cents < 0) {
                throw new InvalidArgumentException('Invalid or duplicate material source.');
            }
            $seen[$source] = true;
            $allocation = [
                'source_line_id' => $source,
                'activity_ref' => $line['activity_ref'] ?? null,
                'building_id' => $line['building_id'] ?? null,
                'zone_id' => $line['zone_id'] ?? null,
                'quantity_milli' => $quantity,
                'budget_cents' => $cents,
            ];
            if (!is_string($code) || trim($code) === ''
                || !is_string($spec) || trim($spec) === ''
                || !is_string($unit) || trim($unit) === '') {
                $unmatched[] = ['reason'=>'identity_incomplete', 'allocation'=>$allocation];
                continue;
            }
            $identity = [strtoupper(trim($code)), trim($spec), trim($unit)];
            $key = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'article_code'=>$identity[0], 'specification'=>$identity[1],
                    'unit'=>$identity[2], 'quantity_milli'=>0,
                    'budget_cents'=>0, 'allocations'=>[],
                ];
            }
            foreach (['quantity_milli'=>$quantity, 'budget_cents'=>$cents] as $field=>$increment) {
                if ($increment > PHP_INT_MAX - $groups[$key][$field]) {
                    throw new InvalidArgumentException('Material total overflow.');
                }
                $groups[$key][$field] += $increment;
            }
            $groups[$key]['allocations'][] = $allocation;
        }
        ksort($groups, SORT_STRING);
        return ['groups'=>array_values($groups), 'unmatched'=>$unmatched];
    }
}
