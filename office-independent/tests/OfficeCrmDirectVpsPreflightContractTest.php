<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$path=$root.'/deployment/vps/crm-direct-project-preflight.php';
$source=file_get_contents($path);
if (!is_string($source)) throw new RuntimeException('Missing VPS direct CRM preflight');
foreach ([
    "'--check'", "posix_geteuid()", "office-test-db.env",
    "brebo-office-test.conf", "brebo_office_test",
    "OFFICE_TEST_CRM_DIRECT_PROJECT_TRANSFER_ENABLED",
    "OFFICE_TEST_CRM_DIRECT_PROJECT_TRANSFER_SCHEMA_VERIFIED",
    'office_crm_direct_project_transfers',
    'office_calc_crm_acceptance_transfers',
    'office_module_permissions',
    'information_schema.COLUMNS',
    'information_schema.KEY_COLUMN_USAGE',
    'PROJECT_PREPARATION_STATUS',
    'PROJECT_AUTO_INCREMENT',
    'CRM_DIRECT_PREFLIGHT=READY_FOR_EXPLICIT_TEST_ACTIVATION',
] as $required) {
    if (!str_contains($source,$required)) throw new RuntimeException('Missing preflight invariant: '.$required);
}
if (preg_match('/\b(?:CREATE|ALTER|DROP|TRUNCATE|DELETE|UPDATE|INSERT)\s+(?:TABLE|INTO|FROM|office_)/i', $source)) {
    throw new RuntimeException('Unexpected mutating SQL in read-only preflight');
}
echo "CRM_DIRECT_VPS_PREFLIGHT_CONTRACT=PASS\n";
