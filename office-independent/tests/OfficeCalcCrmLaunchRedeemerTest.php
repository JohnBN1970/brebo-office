<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmLaunchRedeemer.php';
use Brebo\Office\Calculation\OfficeCalcCrmLaunchRedeemer;
if(!in_array('sqlite',PDO::getAvailableDrivers(),true)){fwrite(STDERR,"SQLite required\n");exit(1);}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach([
 'CREATE TABLE office_operator_accounts(id INTEGER PRIMARY KEY,active INTEGER)',
 'CREATE TABLE office_operator_sessions(token_hash TEXT PRIMARY KEY,revoked_at TEXT,expires_at TEXT)',
 'CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,revision INTEGER,stage TEXT,status TEXT)',
 'CREATE TABLE office_calc_crm_drafts(id INTEGER PRIMARY KEY,opportunity_id INTEGER,crm_revision INTEGER,project_id INTEGER)',
 'CREATE TABLE office_calc_crm_launch_grants(reference TEXT PRIMARY KEY,session_hash TEXT,actor_id INTEGER,draft_id INTEGER,expires_at TEXT,consumed_at TEXT,revoked_at TEXT)',
] as $sql)$db->exec($sql);
$hash=str_repeat('a',64);$ref=str_repeat('b',64);$now=1000;
$db->exec('INSERT INTO office_operator_accounts VALUES(7,1)');
$db->prepare('INSERT INTO office_operator_sessions VALUES(?,NULL,?)')->execute([$hash,gmdate('Y-m-d H:i:s',2000)]);
$db->exec("INSERT INTO office_crm_opportunities VALUES(11,4,'proposal','open')");
$db->exec('INSERT INTO office_calc_crm_drafts VALUES(9,11,4,NULL)');
$db->prepare('INSERT INTO office_calc_crm_launch_grants VALUES(?,?,?,?,?,NULL,NULL)')->execute([$ref,$hash,7,9,gmdate('Y-m-d H:i:s',1090)]);
$redeemer=new OfficeCalcCrmLaunchRedeemer($db);
$allow=static fn(PDO $db,int $actor,int $opp):bool=>$actor===7&&$opp===11;
$deny=static fn(PDO $db,int $actor,int $opp):bool=>false;
if($redeemer->redeem($ref,$hash,7,9,$now,$deny))throw new RuntimeException('Unauthorized grant accepted');
if(!$redeemer->redeem($ref,$hash,7,9,$now,$allow))throw new RuntimeException('Valid grant rejected');
if($redeemer->redeem($ref,$hash,7,9,$now,$allow))throw new RuntimeException('Replay accepted');
echo "CRM_CALC_LAUNCH_REDEEMER=PASS\n";
