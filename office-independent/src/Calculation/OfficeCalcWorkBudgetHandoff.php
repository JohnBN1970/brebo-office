<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use InvalidArgumentException;

/**
 * Commercial CRM calculation to execution work-budget handoff.
 * Pure domain contract: persistence, authorization and project creation belong
 * to the acceptance transaction, not to this mapper.
 */
final class OfficeCalcWorkBudgetHandoff
{
    public static function prepare(array $accepted, array $lines): array
    {
        $crmId = $accepted['crm_opportunity_id'] ?? null;
        $calculationId = $accepted['calculation_id'] ?? null;
        $revision = $accepted['accepted_revision'] ?? null;
        if (!is_string($crmId) || trim($crmId) === ''
            || !is_int($calculationId) || $calculationId <= 0
            || !is_string($revision) || trim($revision) === ''
            || ($accepted['status'] ?? null) !== 'accepted') {
            throw new InvalidArgumentException('Accepted CRM calculation reference required');
        }
        if ($lines === []) {
            throw new InvalidArgumentException('Work budget requires accepted calculation lines');
        }
        $items = [];
        $ids = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                throw new InvalidArgumentException('Invalid calculation line');
            }
            $id = $line['line_id'] ?? null;
            $type = $line['cost_type'] ?? null;
            $quantity = $line['quantity'] ?? null;
            $unitCost = $line['unit_cost_cents'] ?? null;
            if (!is_string($id) || $id === '' || isset($ids[$id])
                || !in_array($type, ['labor', 'material', 'equipment', 'subcontract', 'other'], true)
                || !is_int($quantity) || $quantity < 0
                || !is_int($unitCost) || $unitCost < 0) {
                throw new InvalidArgumentException('Invalid or duplicate accepted calculation line');
            }
            if ($quantity > 0 && $unitCost > intdiv(PHP_INT_MAX, $quantity)) {
                throw new InvalidArgumentException('Accepted calculation line budget overflows integer cents');
            }
            $ids[$id] = true;
            $items[] = [
                'source_line_id' => $id,
                'cost_type' => $type,
                'quantity' => $quantity,
                'unit_cost_cents' => $unitCost,
                'budget_cents' => $quantity * $unitCost,
                'activity_ref' => $line['activity_ref'] ?? null,
            ];
        }
        return [
            'source' => 'accepted_crm_calculation',
            'crm_opportunity_id' => $crmId,
            'calculation_id' => $calculationId,
            'accepted_revision' => $revision,
            'baseline_locked' => true,
            'items' => $items,
            'risk_register' => [],
            'quality_checks' => [],
            'progress_entries' => [],
        ];
    }
}
