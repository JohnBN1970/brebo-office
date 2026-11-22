<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$service=file_get_contents($root.'/src/Approval/OfficeTestProjectCreationPermissionAdmin.php');
$page=file_get_contents($root.'/public/office-project-permission-settings.php');
foreach ([$service,$page] as $source) if (!is_string($source)) throw new RuntimeException('Missing role profile source');
foreach ([
    'applyProjectCreateProfile(', 'OfficeTestRolePermissionPolicy::expectedProjectCreate(',
    "expected?'grant':'revoke'", "'Testprofiel '.$roleCode",
    'return $this->change(', 'OfficeTestSiteAdministratorAccess',
    "office_test_project_create_permission_events",
] as $marker) {
    if (!str_contains($service,$marker)) throw new RuntimeException('Role profile service contract missing: '.$marker);
}
foreach ([
    "if (\$action==='apply_profile')", "role_code", "confirm_role_change",
    'applyProjectCreateProfile(', 'hash_equals($csrf,$submitted)',
    'een <strong>echte, geauditeerde wijziging',
    'Bekijk als is alleen een voorbeeld',
] as $marker) {
    if (!str_contains($page,$marker)) throw new RuntimeException('Role profile HTTP contract missing: '.$marker);
}
echo "OFFICE_TEST_ROLE_PROFILE_APPLY=PASS\n";
