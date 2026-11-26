<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$page=file_get_contents($root.'/public/office-system-diagnostics.php');
$router=file_get_contents($root.'/public/index.php');
$settings=file_get_contents($root.'/public/office-settings.php');
foreach (['OfficeTestSiteAdministratorAccess','->isAuthorized(','brebo_office_test','SELECT DATABASE()','information_schema.TABLES','Cache-Control: no-store','REQUEST_METHOD','OfficeMount::isTest()'] as $required) {
 if (!str_contains($page,$required)) throw new RuntimeException('Missing diagnostics contract: '.$required);
}
if (preg_match('/\b(?:INSERT INTO|UPDATE\s+office_|DELETE FROM|ALTER TABLE|DROP TABLE)\b/i',$page)) throw new RuntimeException('Diagnostics must be read-only');
foreach ([$router,$settings] as $source) if (!str_contains($source,'/office-test/instellingen/systeemdiagnostiek')) throw new RuntimeException('Missing diagnostics route');
echo "OFFICE_SYSTEM_DIAGNOSTICS_CONTRACT=PASS\n";
