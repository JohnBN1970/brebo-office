<?php
declare(strict_types=1);
require __DIR__.'/office_test_environment.php';
require __DIR__.'/OfficeOnSiteRolloutReadiness.php';
if (($argv[1]??'')!=='OFFICE-TEST') throw new RuntimeException('EXPLICIT_TEST_MODE_REQUIRED');
[$base,$path,$config,$active]=officeTestEnvironment();
if (isset($argv[2]) && realpath($argv[2])!==$active) throw new RuntimeException('READINESS_RELEASE_SCOPE_MISMATCH');
$db=officeTestDatabase($config);
// This audit never writes or enables flags. It deliberately prints only counts
// and readiness labels; identities and actual rates stay private in Office.
$report=OfficeOnSiteRolloutReadiness::inspect($db);
foreach ($report as $key=>$value) {
    echo 'ONSITE_READINESS_'.strtoupper($key).'='.$value."\n";
}
echo "ONSITE_READINESS_AUDIT=PASS_READ_ONLY\n";
