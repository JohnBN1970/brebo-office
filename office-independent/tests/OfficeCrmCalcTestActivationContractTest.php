<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$source=file_get_contents($root.'/deployment/vps/activate-crm-calc-test.php');
if (!is_string($source)) throw new RuntimeException('Missing activation helper');
$must=[
    '/srv/brebo/office-test/current',
    '/etc/php/8.3/fpm/pool.d/brebo-office-test.conf',
    'CRM_CALC_SCHEMA_PREFLIGHT=SCHEMA_ONLY_PASS',
    'CRM_CALC_ACTIVATION=PLAN_ONLY_NO_WRITES',
    'CRM_CALC_ACTIVATION=TEST_FLAGS_ENABLED',
    'CRM_CALC_BROWSER_E2E=NOT_VERIFIED',
    'OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED',
    'OFFICE_CALC_CRM_SCHEMA_VERIFIED',
    'CRM_BOARD_VISIBILITY_PATCH_NOT_DEPLOYED',
    'php-fpm8.3 -t',
    'systemctl reload php8.3-fpm',
];
foreach ($must as $marker) if (!str_contains($source,$marker)) throw new RuntimeException('Activation guard missing: '.$marker);
foreach (['/etc/php/8.3/fpm/pool.d/','/srv/brebo/office-test/current','/etc/brebo/office-test-db.env'] as $marker)
    if (!str_contains($source,$marker)) throw new RuntimeException('Wrong environment restriction: '.$marker);
if (str_contains($source,'--force') || str_contains($source,'office.brebobv.nl')) throw new RuntimeException('Unsafe activation option');
echo "CRM_CALC_TEST_ACTIVATION_CONTRACT=PASS\n";
