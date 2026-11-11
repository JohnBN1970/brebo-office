<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$adapter = file_get_contents($root . '/src/Calculation/OfficeCalcAcceptedOpportunityTransfer.php');
$verifier = file_get_contents($root . '/src/Calculation/OfficeCalcPersistedCrmAcceptance.php');
$sql = file_get_contents($root . '/deployment/hostinger/sql/office-crm-calculation-acceptances.sql');
if (!is_string($adapter) || !is_string($verifier) || !is_string($sql)) {
    throw new RuntimeException('Missing persisted CRM acceptance files');
}
foreach ([
    'OfficeCalcCrmTransferService($db)',
    'OfficeCalcPersistedCrmAcceptance::verify($transaction, $candidate)',
] as $required) {
    if (!str_contains($adapter, $required)) {
        throw new RuntimeException('Missing verified acceptance handoff: ' . $required);
    }
}
foreach ([
    'inTransaction()',
    'office_crm_calculation_acceptances',
    'FOR UPDATE',
    'accepted_revision',
    "=== 'accepted'",
] as $required) {
    if (!str_contains($verifier, $required)) {
        throw new RuntimeException('CRM acceptance verifier must fail closed: ' . $required);
    }
}
if (!str_contains($sql, 'UNIQUE KEY uq_crm_calculation_acceptance_opportunity')) {
    throw new RuntimeException('Missing unique opportunity acceptance constraint');
}
echo "CALC_PERSISTED_ACCEPTANCE_ADAPTER=PASS\n";
