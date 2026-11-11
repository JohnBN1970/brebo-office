<?php
declare(strict_types=1);
namespace Brebo\Office\WorkingBudget;

use InvalidArgumentException;

/**
 * Read-only procurement quantities by article allocation.
 * Does not equate receipts with invoices, payments or booked actuals.
 * All quantities use integer thousandths of the source unit.
 */
final class MaterialProcurementProjection
{
    public static function summarize(array $materials, array $orders, array $receipts): array
    {
        $totals = [];
        foreach ($materials as $material) {
            if (!is_array($material)) throw new InvalidArgumentException('Invalid material.');
            $key = self::identity($material);
            if (isset($totals[$key])) throw new InvalidArgumentException('Duplicate material identity.');
            $budget = self::nonnegative($material['quantity_milli'] ?? null);
            $totals[$key] = [
                'article_code'=>$material['article_code'],
                'specification'=>$material['specification'],
                'unit'=>$material['unit'],
                'budget_quantity_milli'=>$budget,
                'ordered_quantity_milli'=>0,
                'received_quantity_milli'=>0,
                'remaining_to_order_milli'=>$budget,
                'outstanding_delivery_milli'=>0,
                'overordered_milli'=>0,
            ];
        }
        $orderQuantities = [];
        $orderIdentity = [];
        foreach ($orders as $order) {
            if (!is_array($order) || !is_string($order['order_line_id'] ?? null)
                || $order['order_line_id'] === '' || isset($orderQuantities[$order['order_line_id']])) {
                throw new InvalidArgumentException('Invalid or duplicate order line.');
            }
            $key = self::identity($order);
            if (!isset($totals[$key])) throw new InvalidArgumentException('Order article not in budget.');
            $qty = self::nonnegative($order['quantity_milli'] ?? null);
            $id = $order['order_line_id'];
            $orderQuantities[$id] = $qty;
            $orderIdentity[$id] = $key;
            self::add($totals[$key]['ordered_quantity_milli'], $qty);
        }
        $receivedByOrder = [];
        $receiptIds = [];
        foreach ($receipts as $receipt) {
            if (!is_array($receipt) || !is_string($receipt['receipt_id'] ?? null)
                || $receipt['receipt_id'] === '' || isset($receiptIds[$receipt['receipt_id']])
                || !is_string($receipt['order_line_id'] ?? null)) {
                throw new InvalidArgumentException('Invalid or duplicate receipt.');
            }
            $receiptIds[$receipt['receipt_id']] = true;
            $orderId = $receipt['order_line_id'];
            if (!isset($orderQuantities[$orderId])) throw new InvalidArgumentException('Unknown receipt order line.');
            $qty = self::nonnegative($receipt['quantity_milli'] ?? null);
            $receivedByOrder[$orderId] ??= 0;
            self::add($receivedByOrder[$orderId], $qty);
            if ($receivedByOrder[$orderId] > $orderQuantities[$orderId]) {
                throw new InvalidArgumentException('Receipt exceeds ordered quantity.');
            }
            self::add($totals[$orderIdentity[$orderId]]['received_quantity_milli'], $qty);
        }
        foreach ($totals as &$item) {
            $ordered = $item['ordered_quantity_milli'];
            $budget = $item['budget_quantity_milli'];
            $item['remaining_to_order_milli'] = max(0, $budget - $ordered);
            $item['overordered_milli'] = max(0, $ordered - $budget);
            $item['outstanding_delivery_milli'] = $ordered - $item['received_quantity_milli'];
        }
        unset($item);
        ksort($totals, SORT_STRING);
        return array_values($totals);
    }

    private static function identity(array $item): string
    {
        foreach (['article_code','specification','unit'] as $field) {
            if (!is_string($item[$field] ?? null) || trim($item[$field]) === '') {
                throw new InvalidArgumentException('Missing verified article identity.');
            }
        }
        return hash('sha256', json_encode([
            strtoupper(trim($item['article_code'])),
            trim($item['specification']), trim($item['unit'])
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private static function nonnegative(mixed $value): int
    {
        if (!is_int($value) || $value < 0) throw new InvalidArgumentException('Invalid quantity.');
        return $value;
    }

    private static function add(int &$total, int $value): void
    {
        if ($value > PHP_INT_MAX - $total) throw new InvalidArgumentException('Quantity overflow.');
        $total += $value;
    }
}
