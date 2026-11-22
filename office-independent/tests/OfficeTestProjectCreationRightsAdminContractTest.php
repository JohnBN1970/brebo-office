<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$page=file_get_contents($root.'/public/office-project-permission-settings.php');
$service=file_get_contents($root.'/src/Approval/OfficeTestProjectCreationPermissionAdmin.php');
$schema=file_get_contents($root.'/schema/office-test-project-create-permission-events.sql');
$router=file_get_contents($root.'/public/index.php');
$blueprints=file_get_contents($root.'/public/office-blueprint-approval-settings.php');
foreach ([$page,$service,$schema,$router,$blueprints] as $source) {
    if (!is_string($source) || $source==='') throw new RuntimeException('Missing test role rights source');
}
$requiredPage=[
    'OfficeMount::isTest()', 'OFFICE_TEST_BLUEPRINT_ADMIN_ENABLED',
    'OFFICE_TEST_BLUEPRINT_ADMIN_CSRF_KEY', 'currentOperator()',
    'OfficeTestSiteAdministratorAccess', 'isAuthorized(', 'hash_equals(',
    'office_test_project_create_permission_events', 'Projectrechten',
    "operator_id", "permission_action", "name=\"reason\"", "method=\"post\"",
];
foreach ($requiredPage as $marker) {
    if (!str_contains($page,$marker)) throw new RuntimeException('Project rights page missing: '.$marker);
}
foreach ([
    'office_operator_accounts', 'a.active=1', "p.module='projects'",
    "p.permission='create'", 'OfficeTestSiteAdministratorAccess',
    'isAuthorized(', 'FOR UPDATE', 'beginTransaction()', 'commit()',
    'rollBack()', 'office_test_project_create_permission_events',
    "action==='grant'", "action==='revoke'",
] as $marker) {
    if (!str_contains($service,$marker)) throw new RuntimeException('Rights service missing: '.$marker);
}
if (str_contains($schema,'INSERT INTO') || str_contains($page,'sessionStorage')
    || str_contains($page,'officePreviewRole')) {
    throw new RuntimeException('Role preview or installation must not grant user permissions');
}
if (!str_contains($router,"'/office-test/instellingen/organisatie/projectrechten' => 'office-project-permission-settings.php'")) {
    throw new RuntimeException('VPS project role rights route missing');
}
if (!str_contains($blueprints,'/office-test/instellingen/organisatie/projectrechten')) {
    throw new RuntimeException('Admin navigation for rights missing');
}
echo "OFFICE_TEST_PROJECT_ROLE_RIGHTS_ADMIN=PASS\n";
