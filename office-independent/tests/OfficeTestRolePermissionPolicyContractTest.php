<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Approval/OfficeTestRolePermissionPolicy.php';
use Brebo\Office\Approval\OfficeTestRolePermissionPolicy;
$roles=OfficeTestRolePermissionPolicy::roles();
foreach (['RL-001'=>true,'RL-002'=>true,'admin'=>true,'medewerker'=>false,'RL-003'=>false,'RL-004'=>false,'RL-005'=>false] as $code=>$right) {
    if (!array_key_exists($code,$roles) || OfficeTestRolePermissionPolicy::expectedProjectCreate($code)!==$right) {
        throw new RuntimeException('Incorrect project-create role contract '.$code);
    }
}
if (OfficeTestRolePermissionPolicy::expectedProjectCreate('actual')!==null
    || OfficeTestRolePermissionPolicy::expectedProjectCreate('unknown')!==null) {
    throw new RuntimeException('Real operator or unknown role must not grant rights');
}
$nav=file_get_contents(dirname(__DIR__).'/src/Intake/OfficeNavigation.php');
$page=file_get_contents(dirname(__DIR__).'/public/office-project-permission-settings.php');
if (!str_contains($nav,'OfficeTestRolePermissionPolicy::roles()')
    || !str_contains($page,'OfficeTestRolePermissionPolicy::roles()')
    || !str_contains($page,'De kolom hierboven toont de daadwerkelijke serverrechten')
    || !str_contains($page,'geen toekenning')) {
    throw new RuntimeException('Role and effective permission UI integration missing');
}
echo "OFFICE_TEST_ROLE_PERMISSION_POLICY=PASS\n";
