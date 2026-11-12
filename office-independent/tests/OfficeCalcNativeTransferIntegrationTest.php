<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$source = file_get_contents($root . '/src/Calculation/OfficeCalcCrmTransferService.php');
if (!is_string($source)) throw new RuntimeException('Missing CRM transfer service');
foreach ([
    "array_key_exists('unit_costs_cents', \$line)",
    'Mixed native and legacy calculation lines',
    'OfficeCalcCostCarrierHandoffMapper::map($lines)',
    'OfficeCalcWorkBudgetHandoff::prepare($acceptance, $lines)',
] as $required) {
    if (!str_contains($source, $required)) {
        throw new RuntimeException('Native calculation transfer integration missing: ' . $required);
    }
}
if (strpos($source, 'OfficeCalcCostCarrierHandoffMapper::map($lines)')
    > strpos($source, 'OfficeCalcWorkBudgetHandoff::prepare($acceptance, $lines)')) {
    throw new RuntimeException('Native rows must be normalized before budget preparation');
}
echo "CALC_NATIVE_TRANSFER_INTEGRATION=PASS\n";
