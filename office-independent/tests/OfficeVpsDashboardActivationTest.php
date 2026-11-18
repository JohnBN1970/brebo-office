<?php
declare(strict_types=1);
// The helper must refuse unprivileged invocation before reading config or making changes.
if(function_exists('posix_geteuid')&&posix_geteuid()!==0){
 $lines=[];$code=0;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../deployment/vps/activate-dashboard-suite.php').' --apply 2>&1',$lines,$code);
 if($code!==10||!str_contains(implode("\n",$lines),'ROOT_CLI_REQUIRED'))throw new RuntimeException('Unprivileged activation accepted');
}
$source=file_get_contents(__DIR__.'/../deployment/vps/activate-dashboard-suite.php');
foreach(['brebo_office_test','EXISTING_ROUTER_ACTIVATION_REQUIRED','DATABASE_IDENTITY_MISMATCH','PREFERENCE_SCHEMA_MISMATCH','FPM_VALIDATION_FAILED_RESTORED','FPM_RELOAD_FAILED_RESTORED','PLAN_ONLY_NO_WRITES'] as $guard)if(!str_contains($source,$guard))throw new RuntimeException('Missing activation boundary');
echo "VPS_DASHBOARD_ACTIVATION_BOUNDARIES_PASS\n";
