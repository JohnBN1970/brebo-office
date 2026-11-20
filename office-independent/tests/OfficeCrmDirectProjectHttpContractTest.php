<?php
declare(strict_types=1);
$page=file_get_contents(dirname(__DIR__).'/public/office-crm-opportunity-transition.php');
$service=file_get_contents(dirname(__DIR__).'/src/Crm/OfficeCrmDirectProjectAcceptance.php');
$adapter=file_get_contents(dirname(__DIR__).'/src/Crm/OfficeCrmNativeProjectAdapter.php');
if ($page===false || $service===false || $adapter===false) throw new RuntimeException('CRM direct project sources missing');
foreach ([
    'OFFICE_TEST_CRM_DIRECT_PROJECT_TRANSFER_ENABLED',
    'OFFICE_TEST_CRM_DIRECT_PROJECT_TRANSFER_SCHEMA_VERIFIED',
    'OfficeMount::isTest()',
    "hash_equals((string)\$_SESSION['crm_opportunity_csrf']",
    "if (\$action==='create_direct_project')",
    "'stage']!=='won'",
    'OfficeCrmDirectProjectAcceptance',
    "office_calc_crm_acceptance_transfers",
    "office_crm_direct_project_transfers",
    'projecten/detail',
] as $token) {
    if (!str_contains($page,$token)) throw new RuntimeException('Missing direct transfer HTTP invariant: '.$token);
}
foreach (["g.module='projects'","g.permission='create'","a.active=1",'new OfficeCrmNativeProjectAdapter'] as $token) {
    if (!str_contains($service,$token)) throw new RuntimeException('Missing project handoff service invariant: '.$token);
}
foreach (["'status'=>'preparation'",'office_project_memberships','inTransaction()'] as $token) {
    if (!str_contains($adapter,$token)) throw new RuntimeException('Missing native adapter invariant: '.$token);
}
echo "CRM_DIRECT_PROJECT_HTTP_CONTRACT=PASS\n";
