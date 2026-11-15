<?php
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__).'/public/office-project.php');
foreach (["OFFICE_TEST_ACTUAL_LABOUR_COSTS_SCHEMA_VERIFIED",
    "OfficeActualLabourCostProjectReader","Werkelijke arbeidskosten niet beschikbaar",
    "actual_labor_costs","is_array(\$financeContracts)"] as $part) {
    if(!str_contains($source,$part))throw new RuntimeException('Missing work budget actual labour guard: '.$part);
}
$panel=file_get_contents(dirname(__DIR__).'/src/WorkingBudget/ProjectBudgetPanel.php');
foreach (['actual_labor_costs', "\$type === 'labor'", "\$actualLabor['entries'] > 0"] as $part) {
    if(!str_contains($panel,$part))throw new RuntimeException('Missing labor row rendering: '.$part);
}
require_once dirname(__DIR__).'/src/Inzet/OfficeActualLabourCostProjectReader.php';
echo "WORK_BUDGET_ACTUAL_LABOUR_READ_CONTRACT_OK\n";
