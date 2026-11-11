<?php
declare(strict_types=1);

namespace Brebo\Office\WorkingBudget;

use InvalidArgumentException;
use RuntimeException;

/**
 * Normalizes canonical OfficeProcurementOrderSource::awardedProjectOrders().
 * No FinanceCenter invoice match exists in this contract: actuals are unknown.
 * A caller MUST NOT present awarded order totals as actual expenditure.
 */
final class OfficeProcurementBudgetAdapter
{
    public static function fromAwardedOrders(int $projectId, array $orders): array
    {
        if ($projectId < 1) throw new InvalidArgumentException('Invalid project.');
        $seen = [];
        $total = 0;
        $lines = [];
        foreach ($orders as $order) {
            if (!is_array($order)
                || ($order['source'] ?? null) !== 'office_procurement_orders'
                || ($order['project_id'] ?? null) !== $projectId
                || ($order['currency'] ?? null) !== 'EUR'
                || ($order['vat_basis'] ?? null) !== 'excluding'
                || !in_array($order['status'] ?? null, ['ordered', 'received'], true)) {
                throw new InvalidArgumentException('Invalid or unauthorized procurement record.');
            }
            $id = filter_var($order['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $building = filter_var($order['building_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $amount = $order['amount_cents'] ?? null;
            if (!is_int($id) || !is_int($building) || !is_int($amount) || $amount < 0 || isset($seen[$id])) {
                throw new InvalidArgumentException('Invalid or duplicate order.');
            }
            if ($amount > PHP_INT_MAX - $total) throw new \OverflowException('Procurement amount overflow.');
            $seen[$id] = true;
            $total += $amount;
            $lines[] = [
                'order_id' => $id,
                'building_id' => $building,
                'awarded_cents' => $amount,
                'actual_cents' => null,
                'open_commitment_cents' => null,
                'invoice_matching_state' => 'not_connected',
            ];
        }
        return [
            'project_id' => $projectId,
            'source' => 'office_procurement_orders',
            'currency' => 'EUR',
            'vat_basis' => 'excluding',
            'awarded_cents' => $total,
            'actual_cents' => null,
            'open_commitment_cents' => null,
            'invoice_matching_state' => 'not_connected',
            'orders' => $lines,
        ];
    }
}
