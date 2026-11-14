<?php
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__).'/public/office-calc-crm-workbench.php');
if(!is_string($source))throw new RuntimeException('Workbench source missing');
foreach([
 'Gezamenlijke calculaties',
 'office_calc_crm_drafts',
 'office-calc-crm-material-panel.php',
 'office-calc-crm-collaboration-panels.php',
 'element-tree',
 'Data · Calculatieregels',
 'Kosten/eh',
 'Subtotaal',
 'expected_revision',
 'office_project_csrf',
 'OfficeCalcCrmDraftLineRepository',
 'Norm niet beschikbaar',
 'Uren niet beschikbaar',
 'Tarief niet beschikbaar'
] as $contract){
 if(!str_contains($source,$contract))throw new RuntimeException('Calc grid regression: '.$contract);
}
if(substr_count($source,"echo '<!doctype")!==2)throw new RuntimeException('List and draft views must both exist');
if(!str_contains($source,'colspan="17"'))throw new RuntimeException('Empty grid column count mismatch');
echo "NATIVE_CALC_GRID_CONTRACT=PASS\n";
