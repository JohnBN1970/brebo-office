<?php
declare(strict_types=1);
namespace Brebo\Office\WorkingBudget;

use InvalidArgumentException;
use RuntimeException;

/** Joins validated Office orders with validated posted Finance costs.
 * A forecast is NOT emitted until Finance data is explicitly confirmed complete.
 */
final class ProcurementCostBridge
{
    public static function join(array $procurement, array $finance, bool $financeComplete): array
    {
        if (($procurement['source'] ?? null) !== 'office_procurement_orders'
            || ($finance['source'] ?? null) !== 'office_financecenter'
            || ($procurement['project_id'] ?? null) !== ($finance['project_id'] ?? null)) {
            throw new InvalidArgumentException('Mismatched canonical Office sources.');
        }
        if (!$financeComplete) {
            return ['state'=>'finance_incomplete','project_id'=>$procurement['project_id'],
                'forecast_ready'=>false,'posted_actual_cents'=>null,'open_commitment_cents'=>null];
        }
        $orders = $procurement['orders'] ?? null;
        $matches = $finance['matched_by_order_cents'] ?? null;
        if (!is_array($orders) || !is_array($matches)) throw new InvalidArgumentException('Missing source lines.');
        $known = [];
        $reconciliation = [];
        foreach ($orders as $order) {
            $id = (string)($order['order_id'] ?? '');
            if ($id === '' || isset($known[$id])) throw new InvalidArgumentException('Duplicate order.');
            $known[$id] = true;
            $actual = $matches[$id] ?? 0;
            if (!is_int($actual) || $actual < 0) throw new InvalidArgumentException('Invalid matched actual.');
            $reconciliation[] = ['order_id'=>$id,'awarded_cents'=>$order['awarded_cents'],'recognized_actual_cents'=>$actual];
        }
        foreach ($matches as $id=>$amount) {
            if (!isset($known[(string)$id])) throw new RuntimeException('Finance references unknown procurement order.');
        }
        $result = ProcurementCommitmentReconciliation::reconcile($reconciliation);
        $unmatched = $finance['unmatched_actual_cents'] ?? null;
        if (!is_int($unmatched) || $unmatched < 0) throw new InvalidArgumentException('Invalid unmatched cost.');
        if ($result['recognized_actual_cents'] > PHP_INT_MAX - $unmatched
            || $result['recognized_actual_cents'] + $unmatched !== ($finance['posted_actual_cents'] ?? null)) {
            throw new RuntimeException('Finance totals do not reconcile.');
        }
        return ['state'=>'reconciled','project_id'=>$procurement['project_id'],
            'forecast_ready'=>$unmatched === 0,
            'posted_actual_cents'=>$finance['posted_actual_cents'],
            'open_commitment_cents'=>$result['open_commitment_cents'],
            'unmatched_actual_cents'=>$unmatched,
            'orders'=>$result['lines']];
    }
}
