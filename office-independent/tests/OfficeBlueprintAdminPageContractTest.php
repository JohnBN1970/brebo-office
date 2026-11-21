<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$page=file_get_contents($root.'/public/office-blueprint-approval-settings.php');
$router=file_get_contents($root.'/public/index.php');
$access=file_get_contents($root.'/src/Approval/OfficeTestSiteAdministratorAccess.php');
$schema=file_get_contents($root.'/schema/office-test-site-administrators.sql');
$assert=static function(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);};
$assert(is_string($page)&&is_string($router)&&is_string($access)&&is_string($schema),'Sources missing');
foreach ([
  "OfficeMount::isTest()",
  "OFFICE_TEST_BLUEPRINT_APPROVAL_MODES_ENABLED",
  "OFFICE_TEST_BLUEPRINT_ADMIN_ENABLED",
  "OFFICE_TEST_BLUEPRINT_ADMIN_CSRF_KEY",
  "hash_equals(",
  "currentOperator()",
  "isAuthorized(",
  "OfficeBlueprintApprovalModeRepository",
  "->set('project_start','startdossier_release'",
] as $required) $assert(str_contains($page,$required),'Missing page security contract: '.$required);
$assert(str_contains($router,"'/office-test/instellingen/blauwdrukken'"),'Route missing');
$assert(str_contains($access,'office_test_site_administrators'),'Dedicated admin source missing');
$assert(str_contains($access,'accounts.active=1'),'Inactive accounts must be denied');
$assert(str_contains($access,'catch (\\Throwable $e)'),'Missing-schema fail-closed behavior missing');
$assert(!str_contains($schema,'INSERT INTO'),'Schema must not auto-grant administrators');
echo "Blueprint admin page contract OK\n";
