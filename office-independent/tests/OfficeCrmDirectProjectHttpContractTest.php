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
// The won Deal screenshot must not show an unexplained missing project button.
// A successful root schema preflight does not grant individual actor permissions.
foreach ([
    'Projectaanmaak is voor deze testomgeving nog niet geactiveerd',
    'Projectaanmaak niet toegestaan voor het huidige Office-account',
    'bevoegdheid Projecten: aanmaken',
    'Er bestaat een overdrachtsregistratie',
    'Maak project voor directe opdracht',
    "g.permission='create'",
    'office_operator_accounts a',
] as $notice) {
    if (!str_contains($page, $notice)) throw new RuntimeException('Missing transfer diagnostic: '.$notice);
}
if (!str_contains($page, "if (!\$directEnabled)") || !str_contains($page, "role=\"status\"")) {
    throw new RuntimeException('Direct project blocked states must be visible and fail closed');
}
echo "CRM_DIRECT_PROJECT_HTTP_CONTRACT=PASS\n";
