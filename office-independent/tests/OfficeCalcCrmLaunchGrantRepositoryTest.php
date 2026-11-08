<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmLaunchGrantRepository.php';
use Brebo\Office\Calculation\OfficeCalcCrmLaunchGrantRepository;
if(!in_array('sqlite',PDO::getAvailableDrivers(),true)){fwrite(STDERR,"SQLite required\n");exit(1);}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_calc_crm_launch_grants(reference TEXT PRIMARY KEY,session_hash TEXT,actor_id INTEGER,draft_id INTEGER,expires_at TEXT,consumed_at TEXT,revoked_at TEXT)');
$repo=new OfficeCalcCrmLaunchGrantRepository($db);
$session=str_repeat('a',64);
$ref=$repo->issue($session,7,9,1000);
if(strlen($ref)!==64)throw new RuntimeException('Grant not issued');
if($repo->consume($ref,$session,8,9,1001))throw new RuntimeException('Wrong actor accepted');
if($repo->consume($ref,str_repeat('b',64),7,9,1001))throw new RuntimeException('Wrong session accepted');
if(!$repo->consume($ref,$session,7,9,1001))throw new RuntimeException('Valid grant rejected');
if($repo->consume($ref,$session,7,9,1001))throw new RuntimeException('Replay accepted');
$expired=$repo->issue($session,7,9,1000);
if($repo->consume($expired,$session,7,9,1090))throw new RuntimeException('Expired grant accepted');
$db->prepare('UPDATE office_calc_crm_launch_grants SET revoked_at=? WHERE reference=?')->execute(['2026-01-01 00:00:00',$expired]);
if($repo->consume($expired,$session,7,9,1001))throw new RuntimeException('Revoked grant accepted');
echo "CRM_CALC_ONE_TIME_LAUNCH_GRANT=PASS\n";
