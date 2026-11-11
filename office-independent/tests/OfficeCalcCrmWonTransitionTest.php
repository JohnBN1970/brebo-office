<?php
declare(strict_types=1);
$source = file_get_contents(dirname(__DIR__) . '/src/Crm/OfficeCrmWonTransition.php');
if (!is_string($source)) throw new RuntimeException('Missing CRM won transition');
foreach ([
    "OfficeCrmFunnel::canTransition(\$from, 'won')",
    "OfficeCalcCrmHandoffDecision::decide('won', \$acceptedCalculation)",
    'Calculated acceptance requires atomic project handoff',
    "->transition(\$opportunityId, \$from, 'won')",
    "'project_created' => false",
] as $required) {
    if (!str_contains($source, $required)) {
        throw new RuntimeException('Missing fail-closed CRM won transition guard: ' . $required);
    }
}
if (strpos($source, 'Calculated acceptance requires atomic project handoff')
    > strpos($source, "->transition(\$opportunityId, \$from, 'won')")) {
    throw new RuntimeException('Calculated acceptance must be blocked before stage change');
}
echo "CRM_WON_TRANSITION_CONTRACT=PASS\n";
