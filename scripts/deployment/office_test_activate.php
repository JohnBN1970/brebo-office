<?php
declare(strict_types=1);
require __DIR__.'/office_test_environment.php';
[$base,$path,$config,$active]=officeTestEnvironment();
$candidate=realpath($argv[1]??'');
if (($argv[2]??'')!=='OFFICE-TEST' || !$candidate || !str_starts_with($candidate,$base.'/release-ci-') || !str_ends_with($candidate,'/private')) throw new RuntimeException('INVALID_TEST_ACTIVATION');
$config['OFFICE_PRIVATE_ROOT']=$candidate;
$config['OFFICE_FINANCE_CORE_ROOT']=$candidate.'/packages/brebo-finance-core';
$config['OFFICE_TEST_PROJECT_KICKSTART_ENABLED']='enabled';
// Read-only native work budget; monetary postings retain their separate rollout gate.
$config['OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED']='enabled';
$config['OFFICE_TEST_WORK_BUDGET_KPIS_ENABLED']='enabled';
$config['OFFICE_TEST_ACTUAL_LABOUR_COSTS_SCHEMA_VERIFIED']='enabled';
// office_test_migrate.php verifies the isolated OnSite rejection table before activation.
// This flag never affects production and does not enable unverified labour-cost posting.
$config['OFFICE_TEST_ONSITE_REJECTION_SCHEMA_VERIFIED']='enabled';
// Prevent configured external mail, banking and payment transports in test releases.
foreach ($config as $key=>$value) {
    if (preg_match('/(?:SMTP|MONEYBIRD|ZOHO|PAYMENT|WEBHOOK|OUTBOUND|BANK_API)/i',$key)) {
        $config[$key]=preg_match('/(?:ENABLED|VERIFIED|ALLOWED)$/',$key)?'disabled':'';
    }
}
$temp=tempnam(dirname($path),'.development-config-');
chmod($temp,0600);file_put_contents($temp,"<?php\nreturn ".var_export($config,true).";\n",LOCK_EX);
if (!rename($temp,$path)) throw new RuntimeException('CONFIG_ACTIVATION_FAILED');
echo "OFFICE_TEST_RELEASE_ACTIVATED\n";
