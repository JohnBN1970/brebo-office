<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcBudgetLineBridge.php';
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcWorkBudgetHandoff.php';
use Brebo\Office\Calculation\OfficeCalcBudgetLineBridge;
use Brebo\Office\Calculation\OfficeCalcWorkBudgetHandoff;
$lines = OfficeCalcBudgetLineBridge::map('L1', '2', ['labor'=>'10.50','material'=>'20.25'], 'A1');
$budget = OfficeCalcWorkBudgetHandoff::prepare([
    'crm_opportunity_id'=>'TEST-1', 'calculation_id'=>1,
    'accepted_revision'=>'rev1', 'status'=>'accepted'
], $lines);
if (count($budget['items']) !== 2 || $budget['items'][0]['budget_cents'] !== 2100 || $budget['items'][1]['budget_cents'] !== 4050 || !$budget['baseline_locked']) {
    throw new RuntimeException('Kostenraster naar vergrendelde werkbegroting klopt niet.');
}
foreach ([['L1','2.5',['labor'=>'10']], ['L1','2',['labor'=>'1.234']], ['L1','2',[]]] as $invalid) {
    try { OfficeCalcBudgetLineBridge::map(...$invalid); }
    catch (DomainException) { continue; }
    throw new RuntimeException('Niet-overdraagbare calculatie werd geaccepteerd.');
}
echo "CALC_BUDGET_LINE_BRIDGE=PASS\n";
