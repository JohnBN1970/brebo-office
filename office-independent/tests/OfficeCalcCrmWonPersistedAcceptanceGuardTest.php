<?php
declare(strict_types=1);
$source = file_get_contents(dirname(__DIR__) . '/src/Crm/OfficeCrmWonTransition.php');
if (!is_string($source)) throw new RuntimeException('Missing won transition');
foreach ([
    'beginTransaction()',
    'office_crm_calculation_acceptances WHERE opportunity_id = ? FOR UPDATE',
    "OfficeCalcCrmHandoffDecision::decide('won', \$acceptedCalculation)",
    'Calculated acceptance requires atomic project handoff',
    'rollBack()',
    'commit()',
] as $required) {
    if (!str_contains($source, $required)) throw new RuntimeException('Missing persisted acceptance guard: ' . $required);
}
if (strpos($source, 'office_crm_calculation_acceptances WHERE opportunity_id = ? FOR UPDATE')
    > strpos($source, "->transition(\$opportunityId, \$from, 'won')")) {
    throw new RuntimeException('Stage must not change before persisted acceptance is read');
}
echo "CRM_WON_PERSISTED_ACCEPTANCE_GUARD=PASS\n";
