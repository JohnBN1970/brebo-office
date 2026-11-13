<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmLaunchGate.php';
use Brebo\Office\Calculation\OfficeCalcCrmLaunchGate;
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {fwrite(STDERR,"SQLite required\n");exit(1);}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$gate=new OfficeCalcCrmLaunchGate($db);
$csrf=str_repeat('x',32);
$rights=static fn(PDO $db,int $actor,int $opp):bool=>true;
foreach ([
 ['','x',$csrf,1,100],
 ['session','',$csrf,1,100],
 ['session','bad',$csrf,1,100],
 ['session',$csrf,$csrf,0,100],
 ['session',$csrf,$csrf,1,0],
 ['session',$csrf,'short',1,100],
] as $args) {
 if($gate->authorize(...[...$args,$rights])!==null)throw new RuntimeException('Invalid launch permitted');
}
// Even valid CSRF/session input must fail closed when session storage is absent.
if($gate->authorize('unverified-session',$csrf,$csrf,1,100,$rights)!==null)
 throw new RuntimeException('Unverified session permitted');
echo "CRM_CALC_LAUNCH_GATE=PASS\n";
