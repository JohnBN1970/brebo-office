<?php
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__).'/src/WorkingBudget/LockedBaselineLines.php');
if(!is_string($source))throw new RuntimeException('Missing locked baseline reader.');
foreach(['information_schema.COLUMNS','quantity_decimal','unit_cost_decimal','budget_decimal','if ($available)','Invalid decimal baseline field'] as $required){
 if(!str_contains($source,$required))throw new RuntimeException('Missing read model contract: '.$required);
}
echo "CALC_DECIMAL_BASELINE_READ_CONTRACT=PASS\n";
