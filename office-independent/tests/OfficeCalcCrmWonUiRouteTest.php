<?php
declare(strict_types=1);
$source = file_get_contents(dirname(__DIR__) . '/public/office-crm-opportunity-transition.php');
if (!is_string($source)) throw new RuntimeException('Missing CRM transition page');
foreach ([
    "if (\$to==='won')",
    'OfficeCrmWonTransition.php',
    'OfficeCrmWonTransition($db))->apply($id',
    'OfficeCrmOpportunityRepository($db))->transition($id',
] as $required) {
    if (!str_contains($source, $required)) {
        throw new RuntimeException('Missing CRM won UI route: ' . $required);
    }
}
echo "CRM_WON_UI_ROUTE=PASS\n";
