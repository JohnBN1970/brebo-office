<?php
declare(strict_types=1);
$path=dirname(__DIR__) . '/src/Calculation/OfficeCalcWorkBudgetSchemaPreflight.php';
$code=file_get_contents($path);
if (!is_string($code)) throw new RuntimeException('Missing schema preflight');
foreach (['information_schema.COLUMNS','DATABASE()','missing_columns','verified'] as $part) {
    if (!str_contains($code,$part)) throw new RuntimeException('Missing preflight requirement: '.$part);
}
foreach (['INSERT INTO','UPDATE ','DELETE FROM','CREATE TABLE','ALTER TABLE','DROP TABLE'] as $mutation) {
    if (str_contains(strtoupper($code),$mutation)) throw new RuntimeException('Preflight must be read-only');
}
echo "CRM_CALC_SCHEMA_PREFLIGHT=PASS\n";
