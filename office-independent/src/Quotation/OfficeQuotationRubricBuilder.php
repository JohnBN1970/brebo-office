<?php
declare(strict_types=1);

namespace Brebo\Office\Quotation;

use InvalidArgumentException;

/**
 * Deterministic, customer-facing rubric proposal from verified Calc sales lines.
 * Never invent technical specifications, quantities, scope or prices.
 * A proposed rubric is NOT an approved rubric.
 */
final class OfficeQuotationRubricBuilder
{
    public static function build(array $verifiedSalesLines): array
    {
        $rubrics = [];
        foreach ($verifiedSalesLines as $index => $line) {
            if (!is_array($line) || !isset($line['description'], $line['quantity'], $line['unit'], $line['amount_ex_vat_cents'])) {
                throw new InvalidArgumentException('Verified sales line required');
            }
            if (!is_numeric($line['quantity']) || !is_finite((float)$line['quantity']) || (float)$line['quantity'] <= 0) {
                throw new InvalidArgumentException('Invalid rubric quantity');
            }
            $amount = $line['amount_ex_vat_cents'];
            if (!is_int($amount) || $amount < 0 || $amount > 1000000000000) {
                throw new InvalidArgumentException('Invalid rubric sales amount');
            }
            // Always derive customer-facing unit price from the verified line total.
            // Never trust an independently supplied unit price on a rubric line.
            $unitPrice = round($amount / 100 / (float)$line['quantity'], 6);
            $label = trim((string)($line['rubric'] ?? 'Werkzaamheden'));
            if ($label === '' || mb_strlen($label, 'UTF-8') > 200) {
                throw new InvalidArgumentException('Invalid rubric');
            }
            if (!isset($rubrics[$label])) {
                $rubrics[$label] = [
                    'rubric' => $label,
                    'description' => 'Wij bieden aan de volgende werkzaamheden uit te voeren:',
                    'lines' => [],
                    'total_ex_vat_cents' => 0,
                    'review' => [
                        'description_checked' => false,
                        'quantities_checked' => false,
                        'prices_checked' => false,
                        'approved' => false,
                    ],
                ];
            }
            $rubrics[$label]['lines'][] = [
                'source_index' => $index,
                'description' => (string)$line['description'],
                'quantity' => $line['quantity'],
                'unit' => (string)$line['unit'],
                'unit_sales_price_ex_vat' => $unitPrice,
                'amount_ex_vat_cents' => $amount,
            ];
            $rubrics[$label]['total_ex_vat_cents'] += $amount;
        }
        return array_values($rubrics);
    }
}
