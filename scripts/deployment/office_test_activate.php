<?php
declare(strict_types=1);
require __DIR__.'/office_test_environment.php';
require __DIR__.'/OfficeOnSiteRolloutReadiness.php';
[$base,$path,$config,$active]=officeTestEnvironment();
$candidate=realpath($argv[1]??'');
if (($argv[2]??'')!=='OFFICE-TEST' || !$candidate || !str_starts_with($candidate,$base.'/release-ci-') || !str_ends_with($candidate,'/private')) throw new RuntimeException('INVALID_TEST_ACTIVATION');
$config['OFFICE_PRIVATE_ROOT']=$candidate;
$config['OFFICE_PERSONAL_DASHBOARD_ENABLED']='enabled';
$config['OFFICE_DASHBOARD_PREFERENCES_SCHEMA_VERIFIED']='enabled';
if(strlen((string)($config['OFFICE_DASHBOARD_CSRF_SECRET']??''))<32)$config['OFFICE_DASHBOARD_CSRF_SECRET']=bin2hex(random_bytes(32));
$config['OFFICE_FINANCE_CORE_ROOT']=$candidate.'/packages/brebo-finance-core';
// These are the existing verified Office-test sales and administration screens.
$config['OFFICE_TEST_UI_V2_ROLLOUT_ALLOWED']='enabled';
$config['OFFICE_TEST_NAV_V2_ENABLED']='enabled';
$config['OFFICE_SALES_ENABLED']='enabled';
$config['OFFICE_SALES_SCHEMA_VERIFIED']='enabled';
$config['OFFICE_FINANCE_CENTER_ENABLED']='enabled';
$config['OFFICE_SALES_SEND_ENABLED']='disabled';
$config['OFFICE_SALES_REMINDERS_ENABLED']='disabled';
$config['OFFICE_TEST_PROJECT_KICKSTART_ENABLED']='enabled';
// Read-only native work budget; monetary postings retain their separate rollout gate.
$config['OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED']='enabled';
$config['OFFICE_TEST_WORK_BUDGET_KPIS_ENABLED']='enabled';
$config['OFFICE_TEST_ACTUAL_LABOUR_COSTS_SCHEMA_VERIFIED']='enabled';
// office_test_migrate.php verifies the isolated OnSite rejection table before activation.
// This flag never affects production and does not enable unverified labour-cost posting.
$config['OFFICE_TEST_ONSITE_REJECTION_SCHEMA_VERIFIED']='enabled';
// OnSite four-eyes is enabled in the isolated test environment only when
// active reviewers have unique, active person mappings for real OnSite projects.
// Existing enabled policy is never downgraded, even if mappings later drift.
// This does not enable monetary labour posting or change any real OnSite data.
$onsiteReadiness=OfficeOnSiteRolloutReadiness::inspect(officeTestDatabase($config));
$wasFourEyes=($config['OFFICE_TEST_ONSITE_FOUR_EYES_REQUIRED']??'')==='enabled';
if (OfficeOnSiteRolloutReadiness::enableFourEyesForTest($onsiteReadiness,$wasFourEyes)) {
    $config['OFFICE_TEST_ONSITE_FOUR_EYES_REQUIRED']='enabled';
    echo "ONSITE_FOUR_EYES_TEST_ACTIVATION=ENABLED\\n";
} else {
    echo "ONSITE_FOUR_EYES_TEST_ACTIVATION=NOT_READY\\n";
}
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
