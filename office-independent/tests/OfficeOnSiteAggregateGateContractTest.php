<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeOnSiteApprovalService.php';
$path=dirname(__DIR__).'/src/Inzet/OfficeOnSiteApprovalService.php';
$source=file_get_contents($path);
foreach ([
    "OFFICE_TEST_LABOUR_SHIFT_AGGREGATE_ENABLED",
    "OFFICE_TEST_LABOUR_BUDGET_DEBIT_ENABLED",
    "Conflicting labour budget posting modes",
    "OfficeLabourShiftAggregatePostingService",
    "postBudgetInsideTransaction((int)\$booking['id'])",
    "postBudgetInsideTransaction(\$bookingId)",
] as $needle) {
    if (!str_contains($source,$needle)) throw new RuntimeException('Missing aggregate gate: '.$needle);
}
echo "ONSITE_AGGREGATE_GATE_CONTRACT_OK\n";
