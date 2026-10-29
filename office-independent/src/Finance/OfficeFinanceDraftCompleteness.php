<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

final class OfficeFinanceDraftCompleteness
{
    public static function isComplete(array $record): bool
    {
        $review = $record['review'] ?? null;
        $lines = $record['review_lines'] ?? null;

        if (!is_array($review) || !is_array($lines)
            || count($lines) < 1 || count($lines) > 30) {
            return false;
        }

        foreach (['supplier_name', 'invoice_number'] as $field) {
            if (!is_string($review[$field] ?? null)
                || trim($review[$field]) === '') {
                return false;
            }
        }

        foreach (['invoice_date', 'due_date'] as $field) {
            $value = $review[$field] ?? '';
            if (!is_string($value)) {
                return false;
            }
            if ($field === 'due_date' && $value === '') {
                continue;
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                return false;
            }
        }

        $cents = static function (mixed $value): ?int {
            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                return null;
            }
            $value = str_replace(',', '.', trim((string)$value));
            if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value)) {
                return null;
            }
            [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
            return (int)$whole * 100 + (int)str_pad($fraction, 2, '0');
        };

        $total = $cents($review['amount_inc_vat'] ?? null);
        $vat = $cents($review['vat_amount'] ?? null);

        if ($total === null || $vat === null || $vat > $total) {
            return false;
        }

        $netSum = 0;
        $vatSum = 0;

        foreach ($lines as $line) {
            if (!is_array($line)
                || trim((string)($line['description'] ?? '')) === '') {
                return false;
            }

            $quantity = str_replace(',', '.', (string)($line['quantity'] ?? ''));
            $rate = str_replace(',', '.', (string)($line['vat_rate'] ?? ''));
            $price = $cents($line['unit_price'] ?? null);
            $net = $cents($line['line_total'] ?? null);

            if (!preg_match('/^\d{1,9}(?:\.\d{1,3})?$/D', $quantity)
                || !preg_match('/^\d{1,3}(?:\.\d{1,3})?$/D', $rate)
                || (float)$rate > 100
                || $price === null || $net === null) {
                return false;
            }

            if (abs((float)$quantity * $price - $net) > 1) {
                return false;
            }

            $netSum += $net;
            $vatSum += (int)round($net * (float)$rate / 100);
        }

        return abs($netSum - ($total - $vat)) <= 1
            && abs($vatSum - $vat) <= 1;
    }
}
