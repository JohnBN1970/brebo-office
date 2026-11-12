<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$factory = file_get_contents($root . '/src/Calculation/OfficeNativeProjectFactory.php');
$handoff = file_get_contents($root . '/src/Calculation/OfficeCalcNativeProjectHandoff.php');
if (!is_string($factory) || !is_string($handoff)) {
    throw new RuntimeException('Missing native project handoff source');
}
foreach ([
    'inTransaction()',
    'office_projects',
    'office_project_memberships',
    'office_operator_accounts',
    'Native project membership table unavailable',
    'Membership requires additional mandatory fields',
    "VALUES (?,?,'manage')",
] as $required) {
    if (!str_contains($factory, $required)) {
        throw new RuntimeException('Missing native project factory guard: ' . $required);
    }
}
if (strpos($factory, 'Membership requires additional mandatory fields')
    > strpos($factory, 'INSERT INTO office_projects')) {
    throw new RuntimeException('Membership schema must be checked before project insert');
}
foreach ([
    'OfficeCalcAcceptedOpportunityTransfer::execute(',
    'OfficeNativeProjectFactory::create($transaction, $project)',
    'expected_crm_stage',
    "UPDATE office_crm_opportunities SET stage='won', status='closed'",
    'return $stmt->rowCount() === 1;',
] as $required) {
    if (!str_contains($handoff, $required)) {
        throw new RuntimeException('Missing atomic handoff wiring: ' . $required);
    }
}
echo "CALC_NATIVE_PROJECT_FACTORY_CONTRACT=PASS\n";
