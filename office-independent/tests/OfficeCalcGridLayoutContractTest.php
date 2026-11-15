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
 'name="labor_norm"',
 'name="labor_hours"',
 'name="labor_hourly_rate"',
 'name="labor_driver"',
 'OFFICE_CALC_LABOR_SCHEMA_VERIFIED',
 'Vraaggestuurd calculeren',
 'guidedOptions',
 'guidedPreset',
 'calc-line-editor',
 'Kraan nodig?',
 'Steiger nodig?',
 'Bouwtoilet nodig?',
 'Extra arbeidsuren nodig?',
 'Extra transport nodig?',
 'Materiaalverlies voorzien?',
 'Bescherming werkgebied nodig?',
 'Geen automatische boeking',
 'material_loss_percent',
 'extra_labor_hours',
 'OfficeCalcQuantityAdjustments',
 'INVALID_MATERIAL_LOSS',
 'INVALID_EXTRA_HOURS',
 'ADJUSTMENT_CONFLICT',
 'ADJUSTMENT_SCHEMA_UNVERIFIED',
 'OFFICE_CALC_QUANTITY_ADJUSTMENTS_SCHEMA_VERIFIED'
] as $contract){
 if(!str_contains($source,$contract))throw new RuntimeException('Calc grid regression: '.$contract);
}
if(!str_contains($source,'grid-template-columns:minmax(0,1fr) 300px') && !str_contains($source,'.gridlayout{display:block}'))throw new RuntimeException('Grid layout missing');
if(substr_count($source,"echo '<!doctype")!==2)throw new RuntimeException('List and draft views must both exist');
if(!str_contains($source,'colspan="19"'))throw new RuntimeException('Empty grid column count mismatch');
foreach(['calc-inline-new','calc-inline-row','Verliesfactor %','name="material_loss_percent"'] as $inline)if(!str_contains($source,$inline))throw new RuntimeException('Inline entry missing: '.$inline);
foreach(['calc-topbar','Terug naar hoofdmenu','calc-account-menu','/office-test/uitloggen','calc-topbar-settings','calc-inline-material-picker'] as $uiContract){
 if(!str_contains($source,$uiContract))throw new RuntimeException('Calculator UI regression: '.$uiContract);
}
if(substr_count($source,"require __DIR__.'/office-calc-crm-material-panel.php'")!==1)throw new RuntimeException('Material picker must render once in grid');
if(!str_contains($source,'<tr class="calc-material-row"><td colspan="19">'))throw new RuntimeException('Material picker must appear inside calculation table');
if(strpos($source,'calc-material-row')<strpos($source,'Nieuwe regel direct onderaan de tabel invullen'))throw new RuntimeException('Material picker must follow row-entry toolbar');
echo "NATIVE_CALC_GRID_CONTRACT=PASS\n";
