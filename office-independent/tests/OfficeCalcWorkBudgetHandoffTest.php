<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcWorkBudgetHandoff.php';
use Brebo\Office\Calculation\OfficeCalcWorkBudgetHandoff as H;
$accepted = ['crm_opportunity_id'=>'crm-123','calculation_id'=>7,'accepted_revision'=>'v2','status'=>'accepted'];
$lines = [['line_id'=>'L1','cost_type'=>'labor','quantity'=>12,'unit_cost_cents'=>4500,'activity_ref'=>'A1']];
$budget = H::prepare($accepted, $lines);
if ($budget['items'][0]['budget_cents'] !== 54000 || !$budget['baseline_locked']
    || $budget['crm_opportunity_id'] !== 'crm-123'
    || $budget['risk_register'] !== [] || $budget['quality_checks'] !== []
    || $budget['progress_entries'] !== []) throw new RuntimeException('Incorrect work budget handoff');
foreach ([
    [['status'=>'draft'] + $accepted, $lines],
    [$accepted, []],
    [$accepted, [$lines[0], $lines[0]]],
] as [$context,$rows]) {
    try { H::prepare($context,$rows); throw new RuntimeException('Invalid handoff accepted'); }
    catch (InvalidArgumentException $expected) {}
}
echo "CRM_CALC_WORK_BUDGET_HANDOFF=PASS\n";
