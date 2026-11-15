<?php
declare(strict_types=1);
require __DIR__.'/office_test_environment.php';
[$base,$path,$config,$active]=officeTestEnvironment();
$candidate=realpath($argv[1]??'');
if (($argv[2]??'')!=='OFFICE-TEST' || !$candidate || !str_starts_with($candidate,$base.'/release-ci-') || !str_ends_with($candidate,'/private')) throw new RuntimeException('INVALID_TEST_ACTIVATION');
$config['OFFICE_PRIVATE_ROOT']=$candidate;
$config['OFFICE_TEST_PROJECT_KICKSTART_ENABLED']='enabled';
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
