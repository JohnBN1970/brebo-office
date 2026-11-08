<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Intake/OfficeNavigation.php';
require_once dirname(__DIR__).'/src/Intake/OfficeMount.php';
use Brebo\Office\Intake\OfficeNavigation;
use Brebo\Office\Intake\OfficeMount;
$check=static function(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);};
putenv('OFFICE_BASE_PATH=/office-test');
putenv('OFFICE_TEST_GOVERNANCE_ENABLED=enabled');
putenv('OFFICE_TEST_GOVERNANCE_SCHEMA_VERIFIED=disabled');
$check(!str_contains(OfficeNavigation::mainV2('/office-test/'),'/office-test/risicos'),'schema gate');
putenv('OFFICE_TEST_GOVERNANCE_SCHEMA_VERIFIED=enabled');
foreach(['kwaliteit','risicos','inkoop'] as $route){
    $check(str_contains(OfficeNavigation::mainV2('/office-test/'.$route),'/office-test/'.$route),'native menu destination');
    $check(str_contains(OfficeNavigation::main($route),'/office-test/'.$route),'legacy menu destination');
    $check(OfficeMount::route($route,['project_id'=>10])==='/office-test/'.$route.'?project_id=10','native context route');
}
putenv('OFFICE_BASE_PATH=/office');
$check(!OfficeNavigation::governanceVisible(),'test rollout does not leak to production');
echo "PASS native navigation: deployment gates and project context\n";
