<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcCrmHandoffDecision.php';
use Brebo\Office\Calculation\OfficeCalcCrmHandoffDecision as Decision;
$accepted = ['status' => 'accepted', 'calculation_id' => 9, 'accepted_revision' => 'v2'];
foreach ([
    ['opportunity', null, 'awaiting_crm_acceptance'],
    ['proposal', $accepted, 'awaiting_crm_acceptance'],
    ['won', null, 'direct_assignment_without_budget'],
    ['won', $accepted, 'transfer_accepted_calculation'],
] as [$stage, $acceptance, $expected]) {
    if (Decision::decide($stage, $acceptance) !== $expected) {
        throw new RuntimeException('Incorrect CRM handoff branch: ' . $stage);
    }
}
foreach ([
    ['status' => 'draft', 'calculation_id' => 9, 'accepted_revision' => 'v2'],
    ['status' => 'accepted', 'calculation_id' => 9, 'accepted_revision' => ''],
] as $invalid) {
    try { Decision::decide('won', $invalid); }
    catch (InvalidArgumentException $expected) { continue; }
    throw new RuntimeException('Unaccepted calculation transferred');
}
echo "CALC_CRM_OPTIONAL_HANDOFF=PASS\n";
