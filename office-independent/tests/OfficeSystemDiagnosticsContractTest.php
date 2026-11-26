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
foreach (['information_schema.COLUMNS','array_diff($columns,$available)','array_key_exists($selected,$groups)','navigator.clipboard.writeText','script-src','random_bytes(18)','geen functionele ketentest','Modulerechten, koppelingen en achtergrondprocessen: niet gecontroleerd.','htmlspecialchars','rawurlencode($selected)'] as $required) {
 if (!str_contains($page,$required)) throw new RuntimeException('Missing module diagnostics contract: '.$required);
}
foreach (['shell_exec(','proc_open(','passthru(','system(','phpinfo('] as $forbidden) {
 if (str_contains($page,$forbidden)) throw new RuntimeException('Unsafe diagnostics operation: '.$forbidden);
}
echo "OFFICE_SYSTEM_DIAGNOSTICS_CONTRACT=PASS\n";
