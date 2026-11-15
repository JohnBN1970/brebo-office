<?php
declare(strict_types=1);
$sql=file_get_contents(dirname(__DIR__).'/deployment/hostinger/sql/office-calc-crm-first-readonly-preflight.sql');
if (!is_string($sql)) throw new RuntimeException('Missing Calc CRM preflight');
foreach ([
    'information_schema.COLUMNS',
    'information_schema.STATISTICS',
    'office_calc_crm_drafts',
    'office_crm_opportunities',
    'office_calculation_id',
    'calculation_versions',
    'calculation_lines',
    'non_unique',
] as $required) {
    if (!str_contains($sql,$required)) throw new RuntimeException('Preflight requirement absent');
}
if (preg_match('/\b(?:CREATE|ALTER|DROP|DELETE|UPDATE|INSERT|REPLACE|TRUNCATE)\s+(?:TABLE|INTO|FROM|[a-z_])/i',$sql)===1) {
    // Ignore explanatory comments; reject any DDL/DML statement in executable SQL.
    $withoutComments=preg_replace('/^\s*--.*$/m','',$sql);
    if (is_string($withoutComments) &&
        preg_match('/\b(?:CREATE|ALTER|DROP|DELETE|UPDATE|INSERT|REPLACE|TRUNCATE)\s+(?:TABLE|INTO|FROM|[a-z_])/i',$withoutComments)===1) {
        throw new RuntimeException('Preflight may not modify data');
    }
}
echo "CRM_FIRST_READONLY_SCHEMA_PREFLIGHT=PASS\n";
