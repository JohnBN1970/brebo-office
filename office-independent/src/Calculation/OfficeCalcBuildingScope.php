<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use InvalidArgumentException;

/**
 * Standalone calculation scope: building and dwelling-type assumptions are
 * captured before pricing. No CRM, Project or Drupal dependency.
 */
final class OfficeCalcBuildingScope
{
    public static function normalize(array $input): array
    {
        $mode = $input['work_type'] ?? null;
        $occupancy = $input['occupancy'] ?? null;
        if (!in_array($mode, ['new_build', 'renovation'], true)
            || !in_array($occupancy, ['occupied', 'vacant', 'mixed'], true)) {
            throw new InvalidArgumentException('Work type and occupancy are required');
        }
        $floors = $input['floors'] ?? null;
        if (!is_int($floors) || $floors < 1 || $floors > 200) {
            throw new InvalidArgumentException('Invalid number of floors');
        }
        $access = $input['access'] ?? null;
        if (!in_array($access, ['ground', 'scaffold', 'lift', 'rope', 'mixed'], true)) {
            throw new InvalidArgumentException('Access method required');
        }
        $types = $input['dwelling_types'] ?? null;
        if (!is_array($types) || $types === []) {
            throw new InvalidArgumentException('At least one dwelling type required');
        }
        $seen = [];
        $normalized = [];
        $total = 0;
        foreach ($types as $type) {
            if (!is_array($type)) {
                throw new InvalidArgumentException('Invalid dwelling type');
            }
            $code = $type['code'] ?? null;
            $count = $type['count'] ?? null;
            if (!is_string($code) || !preg_match('/^[A-Za-z0-9_-]{1,40}$/D', $code)
                || isset($seen[$code]) || !is_int($count) || $count < 1 || $count > 100000) {
                throw new InvalidArgumentException('Invalid or duplicate dwelling type');
            }
            $seen[$code] = true;
            $total += $count;
            if ($total > 100000) {
                throw new InvalidArgumentException('Dwelling count exceeds supported limit');
            }
            $normalized[] = ['code' => $code, 'count' => $count];
        }
        $declared = $input['building_dwelling_count'] ?? null;
        if ($declared !== null && (!is_int($declared) || $declared !== $total)) {
            throw new InvalidArgumentException('Dwelling-type quantities do not reconcile with building');
        }
        return [
            'work_type' => $mode,
            'occupancy' => $occupancy,
            'floors' => $floors,
            'access' => $access,
            'dwelling_types' => $normalized,
            'dwelling_count' => $total,
            'source' => 'calculation_scope',
        ];
    }
}
