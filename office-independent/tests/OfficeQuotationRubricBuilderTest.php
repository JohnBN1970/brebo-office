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
echo "OFFERTSTUDIO_RUBRIC_TEST_OK\n";
