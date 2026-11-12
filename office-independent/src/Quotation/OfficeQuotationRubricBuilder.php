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
                'unit_sales_price_ex_vat' => $line['unit_sales_price_ex_vat'] ?? round((int)$line['amount_ex_vat_cents'] / 100 / (float)$line['quantity'], 6),
                'amount_ex_vat_cents' => (int)$line['amount_ex_vat_cents'],
            ];
            $rubrics[$label]['total_ex_vat_cents'] += (int)$line['amount_ex_vat_cents'];
        }
        return array_values($rubrics);
    }
}
