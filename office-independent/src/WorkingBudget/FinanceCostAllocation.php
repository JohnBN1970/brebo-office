<?php
declare(strict_types=1);

namespace Brebo\Office\WorkingBudget;

use InvalidArgumentException;
use OverflowException;

/**
 * Pure contract for future canonical Office FinanceCenter posted invoice lines.
 * The adapter feeding this MUST enforce Office project ACL and unique posted
 * invoice-line identity. No Drupal, Moneybird or bank payment reads permitted.
 *
 * Input: project_id, source, invoice_line_id, order_id, amount_cents,
 * status, currency, vat_basis. Unmatched lines are reported, never silently
 * assigned to an order. All amounts are positive EUR cents excluding VAT.
 */
final class FinanceCostAllocation
{
    public static function summarize(int $projectId, array $postedLines): array
    {
        if ($projectId < 1) throw new InvalidArgumentException('Invalid project.');
        $seen = [];
        $byOrder = [];
        $unmatched = 0;
        $total = 0;
        $unmatchedCount = 0;
        $byCostType = array_fill_keys(['labor', 'material', 'equipment', 'subcontract', 'other'], 0);
        $unclassified = 0;
        foreach ($postedLines as $line) {
            if (!is_array($line)
                || ($line['source'] ?? null) !== 'office_financecenter'
                || ($line['project_id'] ?? null) !== $projectId
                || ($line['status'] ?? null) !== 'posted'
                || ($line['currency'] ?? null) !== 'EUR'
                || ($line['vat_basis'] ?? null) !== 'excluding') {
                throw new InvalidArgumentException('Invoice line is not a canonical posted Office cost.');
            }
            $id = filter_var($line['invoice_line_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            $amount = $line['amount_cents'] ?? null;
            if (!is_int($id) || isset($seen[$id]) || !is_int($amount) || $amount < 0) {
                throw new InvalidArgumentException('Invalid or duplicate invoice line.');
            }
            $seen[$id] = true;
            $total = self::add($total, $amount);
            $costType = $line['cost_type'] ?? null;
            if ($costType === null) {
                $unclassified = self::add($unclassified, $amount);
            } elseif (!is_string($costType) || !array_key_exists($costType, $byCostType)) {
                throw new InvalidArgumentException('Unknown FinanceCenter cost type.');
            } else {
                $byCostType[$costType] = self::add($byCostType[$costType], $amount);
            }
            $order = $line['order_id'] ?? null;
            if ($order === null) {
                $unmatched = self::add($unmatched, $amount);
                $unmatchedCount++;
                continue;
            }
            $orderId = filter_var($order, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if (!is_int($orderId)) throw new InvalidArgumentException('Invalid order link.');
            $key = (string)$orderId;
            $byOrder[$key] = self::add($byOrder[$key] ?? 0, $amount);
        }
        return [
            'project_id'=>$projectId,
            'source'=>'office_financecenter',
            'posted_actual_cents'=>$total,
            'matched_by_order_cents'=>$byOrder,
            'unmatched_actual_cents'=>$unmatched,
            'unmatched_line_count'=>$unmatchedCount,
            'classified_actual_cents'=>$unclassified === 0 ? $byCostType : null,
            'unclassified_actual_cents'=>$unclassified,
        ];
    }

    private static function add(int $a, int $b): int
    {
        if ($b > PHP_INT_MAX - $a) throw new OverflowException('Cost overflow.');
        return $a + $b;
    }
}
