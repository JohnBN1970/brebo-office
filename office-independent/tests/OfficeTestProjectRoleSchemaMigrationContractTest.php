<?php
declare(strict_types=1);
$script=file_get_contents(dirname(__DIR__).'/deployment/vps/project-role-rights-migrate.php');
if (!is_string($script)) throw new RuntimeException('Migrate command unavailable');
foreach ([
    "PHP_SAPI!=='cli'",'posix_geteuid', "'--plan'","'--apply'",
    'CONFIRM-OFFICE-TEST-ROLE-SCHEMA','/srv/brebo/office-test/current',
    '/etc/brebo/office-test-db.env','brebo_office_test',
    'office-test-site-administrators.sql','office-test-project-create-permission-events.sql',
    'information_schema.COLUMNS','EXISTING_SCHEMA_MISMATCH',
    'PLAN_ONLY_NO_WRITES','READY_NO_PERMISSIONS_GRANTED',
] as $marker) {
    if (!str_contains($script,$marker)) throw new RuntimeException('Migration missing protection '.$marker);
}
if (str_contains($script,'INSERT INTO office_module_permissions')
    || str_contains($script,'UPDATE office_operator_accounts')
    || str_contains($script,'INSERT INTO office_test_site_administrators')) {
    throw new RuntimeException('Schema migration must never grant rights');
}
echo "OFFICE_TEST_PROJECT_ROLE_SCHEMA_MIGRATION=PASS\n";
