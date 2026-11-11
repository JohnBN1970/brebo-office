<?php
declare(strict_types=1);

namespace Brebo\Office\WorkingBudget;

use InvalidArgumentException;

/**
 * Reconciles native Office procurement commitments against Office-booked actuals.
 * All figures in integer EUR cents excluding VAT.
 *
 * Each order: order_id, awarded_cents, recognized_actual_cents.
 * recognized_actual_cents must be sourced from uniquely matched, posted Office
 * purchase invoice lines; NEVER from Moneybird or payment/bank transactions.
 * Unmatched invoice costs must be reported separately by the caller.
 */
final class ProcurementCommitmentReconciliation
{
    public static function reconcile(array $orders): array
    {
        $seen = [];
        $awarded = 0;
        $recognized = 0;
        $open = 0;
        $lines = [];
        foreach ($orders as $order) {
            if (!is_array($order) || !isset($order['order_id']) || !is_scalar($order['order_id'])) {
                throw new InvalidArgumentException('Missing order ID.');
            }
            $id = (string) $order['order_id'];
            if ($id === '' || isset($seen[$id])) {
                throw new InvalidArgumentException('Duplicate or empty order ID.');
            }
            $seen[$id] = true;
            foreach (['awarded_cents', 'recognized_actual_cents'] as $field) {
                if (!isset($order[$field]) || !is_int($order[$field]) || $order[$field] < 0) {
                    throw new InvalidArgumentException($field . ' must be nonnegative integer cents.');
                }
            }
            $value = $order['awarded_cents'];
            $actual = $order['recognized_actual_cents'];
            // Overruns are actual costs, not negative future commitments.
            $remaining = max(0, $value - $actual);
            $awarded = self::add($awarded, $value);
            $recognized = self::add($recognized, $actual);
            $open = self::add($open, $remaining);
            $lines[] = [
                'order_id' => $id,
                'awarded_cents' => $value,
                'recognized_actual_cents' => $actual,
                'open_commitment_cents' => $remaining,
                'overrun_cents' => max(0, $actual - $value),
            ];
        }
        return [
            'awarded_cents' => $awarded,
            'recognized_actual_cents' => $recognized,
            'open_commitment_cents' => $open,
            'lines' => $lines,
        ];
    }

    private static function add(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw new \OverflowException('Amount exceeds integer range.');
        }
        return $left + $right;
    }
}
