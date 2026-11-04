<?php
declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/src/Intake/OfficeCanonicalIntakeRepository.php');
if (!is_string($source)) {
    throw new RuntimeException('repository_source_unavailable');
}
foreach ([
    'final class OfficeCanonicalIntakeRepository',
    'OfficeIntakeProcessPlan::prepare',
    'beginTransaction()',
    'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
    'FOR UPDATE',
    'intake_retry_conflict',
    'intake_retry_disciplines_conflict',
    'office_intake_request_disciplines',
    'office_intake_events',
    'commit()',
    'rollBack()',
] as $needle) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException('missing_repository_contract_' . $needle);
    }
}
if (str_contains($source, 'CREATE TABLE') || str_contains($source, 'DROP TABLE')) {
    throw new RuntimeException('repository_performs_schema_mutations');
}
echo "OfficeCanonicalIntakeRepositoryContractTest OK (12 checks)\n";
