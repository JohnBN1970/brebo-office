<?php
declare(strict_types=1);
$source = file_get_contents(dirname(__DIR__) . '/src/Calculation/OfficeCalcAcceptanceSchemaPreflight.php');
if (!is_string($source)) throw new RuntimeException('Missing schema preflight');
foreach (['SHOW COLUMNS FROM office_crm_calculation_acceptances', 'missing_columns', "'accepted_revision'", "'accepted_by'"] as $required) {
    if (!str_contains($source, $required)) throw new RuntimeException('Missing acceptance schema guard: ' . $required);
}
foreach (['CREATE TABLE', 'ALTER TABLE', 'DROP TABLE', 'INSERT INTO', 'UPDATE '] as $forbidden) {
    if (str_contains($source, $forbidden)) throw new RuntimeException('Schema preflight must remain read-only');
}
echo "CALC_ACCEPTANCE_SCHEMA_PREFLIGHT=PASS\n";
