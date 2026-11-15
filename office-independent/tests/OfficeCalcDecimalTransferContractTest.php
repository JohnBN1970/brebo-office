<?php
declare(strict_types=1);
$service = file_get_contents(dirname(__DIR__).'/src/Calculation/OfficeCalcCrmTransferService.php');
if (!is_string($service)) throw new RuntimeException('Missing CRM transfer service.');
foreach (['quantity_decimal, unit_cost_decimal, budget_decimal','Decimal work-budget schema not installed','Cannot mix legacy and decimal budget lines','Decimal work-budget total mismatch','Decimal budget cents do not match the source calculation.','office_calc_work_budget_lines'] as $part) {
    if (!str_contains($service, $part)) throw new RuntimeException('Missing decimal transfer contract: '.$part);
}
if (!str_contains($service, 'if ($hasDecimals)')) throw new RuntimeException('Legacy transfer compatibility gate missing.');
echo "CALC_DECIMAL_TRANSFER_CONTRACT=PASS\n";
