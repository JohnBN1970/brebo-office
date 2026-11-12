<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Quotation/OfficeQuotationRubricBuilder.php';

use Brebo\Office\Quotation\OfficeQuotationRubricBuilder;

$items = [
    ['rubric' => 'Kozijnen', 'description' => 'Kunststof kozijn', 'quantity' => 12.0, 'unit' => 'st', 'amount_ex_vat_cents' => 120000],
    ['rubric' => 'Kozijnen', 'description' => 'HR++ glas', 'quantity' => 42.5, 'unit' => 'm2', 'amount_ex_vat_cents' => 300000],
    ['rubric' => 'Montage', 'description' => 'Montagewerk', 'quantity' => 16.0, 'unit' => 'uur', 'amount_ex_vat_cents' => 80000],
];
$rubrics = OfficeQuotationRubricBuilder::build($items);
if (count($rubrics) !== 2 || count($rubrics[0]['lines']) !== 2
    || $rubrics[0]['total_ex_vat_cents'] !== 420000
    || $rubrics[0]['lines'][1]['quantity'] !== 42.5
    || $rubrics[0]['lines'][1]['source_index'] !== 1
    || $rubrics[0]['review']['approved'] !== false
    || $rubrics[1]['review']['quantities_checked'] !== false) {
    throw new RuntimeException('Rubric grouping, source fidelity or review gate failed');
}
if ($rubrics[0]['lines'][0]['unit_sales_price_ex_vat'] !== 100.0
    || abs($rubrics[0]['lines'][1]['unit_sales_price_ex_vat'] - 70.588235) > 0.000001
    || $rubrics[1]['lines'][0]['unit_sales_price_ex_vat'] !== 50.0) {
    throw new RuntimeException('Rubric unit selling price missing or incorrect');
}
foreach ($rubrics as $rubric) {
    foreach ($rubric['lines'] as $line) {
        foreach (['cost_price', 'margin', 'purchase'] as $privateField) {
            if (array_key_exists($privateField, $line)) throw new RuntimeException('Private price field leaked');
        }
    }
}
$invalid = $items;
$invalid[0]['quantity'] = '1e309';
try {
    OfficeQuotationRubricBuilder::build($invalid);
    throw new RuntimeException('Non-finite rubric quantity accepted');
} catch (InvalidArgumentException $e) {
    if ($e->getMessage() === 'Non-finite rubric quantity accepted') throw $e;
}
echo "OFFERTSTUDIO_RUBRIC_TEST_OK\n";
