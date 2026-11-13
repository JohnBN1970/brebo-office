<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmLaunchRedeemer.php';
use Brebo\Office\Calculation\OfficeCalcCrmLaunchRedeemer;
if(!in_array('sqlite',PDO::getAvailableDrivers(),true)){fwrite(STDERR,"SQLite required\n");exit(1);}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach([
 'CREATE TABLE office_operator_accounts(id INTEGER PRIMARY KEY,active INTEGER,can_edit_crm INTEGER)',
 'CREATE TABLE office_operator_sessions(token_hash TEXT PRIMARY KEY,operator_id INTEGER,revoked_at TEXT,expires_at TEXT)',
 'CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,revision INTEGER,stage TEXT,status TEXT)',
 'CREATE TABLE office_calc_crm_drafts(id INTEGER PRIMARY KEY,opportunity_id INTEGER,crm_revision INTEGER,project_id INTEGER)',
 'CREATE TABLE office_calc_crm_launch_grants(reference TEXT PRIMARY KEY,session_hash TEXT,actor_id INTEGER,draft_id INTEGER,expires_at TEXT,consumed_at TEXT,revoked_at TEXT)',
] as $sql)$db->exec($sql);
$hash=str_repeat('a',64);$ref=str_repeat('b',64);$now=1000;
$db->exec('INSERT INTO office_operator_accounts VALUES(7,1,1)');
$db->prepare('INSERT INTO office_operator_sessions VALUES(?,7,NULL,?)')->execute([$hash,gmdate('Y-m-d H:i:s',2000)]);
$db->exec("INSERT INTO office_crm_opportunities VALUES(11,4,'proposal','open')");
$db->exec('INSERT INTO office_calc_crm_drafts VALUES(9,11,4,NULL)');
$db->prepare('INSERT INTO office_calc_crm_launch_grants VALUES(?,?,?,?,?,NULL,NULL)')->execute([$ref,$hash,7,9,gmdate('Y-m-d H:i:s',1090)]);
$redeemer=new OfficeCalcCrmLaunchRedeemer($db);
$allow=static fn(PDO $db,int $actor,int $opp):bool=>$actor===7&&$opp===11;
$deny=static fn(PDO $db,int $actor,int $opp):bool=>false;
if($redeemer->redeem($ref,$hash,7,9,$now,$deny))throw new RuntimeException('Unauthorized grant accepted');
if(!$redeemer->redeem($ref,$hash,7,9,$now,$allow))throw new RuntimeException('Valid grant rejected');
if($redeemer->redeem($ref,$hash,7,9,$now,$allow))throw new RuntimeException('Replay accepted');
// Permission changes invalidate grants after issuance.
$db->exec('UPDATE office_operator_accounts SET can_edit_crm=0 WHERE id=7');
$permissionRef=str_repeat('d',64);
$db->prepare('INSERT INTO office_calc_crm_launch_grants VALUES(?,?,?,?,?,NULL,NULL)')
    ->execute([$permissionRef,$hash,7,9,gmdate('Y-m-d H:i:s',1090)]);
if($redeemer->redeem($permissionRef,$hash,7,9,$now,$allow))throw new RuntimeException('CRM rights revoked but grant accepted');
$db->exec('UPDATE office_operator_accounts SET can_edit_crm=1 WHERE id=7');
$db->exec('UPDATE office_operator_sessions SET operator_id=8');
if($redeemer->redeem($permissionRef,$hash,7,9,$now,$allow))throw new RuntimeException('Session operator mismatch accepted');
$db->exec('UPDATE office_operator_sessions SET operator_id=7');
// Revocation and stale CRM context must invalidate even unconsumed grants.
$next=str_repeat('c',64);
$db->prepare('INSERT INTO office_calc_crm_launch_grants VALUES(?,?,?,?,?,NULL,NULL)')
    ->execute([$next,$hash,7,9,gmdate('Y-m-d H:i:s',1090)]);
$db->exec('UPDATE office_operator_sessions SET revoked_at="2026-01-01 00:00:00"');
if($redeemer->redeem($next,$hash,7,9,$now,$allow))throw new RuntimeException('Revoked Office session accepted');
$db->exec('UPDATE office_operator_sessions SET revoked_at=NULL');
$db->exec('UPDATE office_crm_opportunities SET revision=5 WHERE id=11');
if($redeemer->redeem($next,$hash,7,9,$now,$allow))throw new RuntimeException('Stale CRM revision accepted');
$db->exec('UPDATE office_crm_opportunities SET revision=4 WHERE id=11');
$db->exec('UPDATE office_operator_accounts SET active=0 WHERE id=7');
if($redeemer->redeem($next,$hash,7,9,$now,$allow))throw new RuntimeException('Inactive operator accepted');
$db->exec('UPDATE office_operator_accounts SET active=1 WHERE id=7');
$db->exec('UPDATE office_calc_crm_drafts SET project_id=25 WHERE id=9');
if($redeemer->redeem($next,$hash,7,9,$now,$allow))throw new RuntimeException('Transferred draft accepted');
$db->exec('UPDATE office_calc_crm_drafts SET project_id=NULL WHERE id=9');
$db->exec("UPDATE office_crm_opportunities SET stage='won',status='closed' WHERE id=11");
if($redeemer->redeem($next,$hash,7,9,$now,$allow))throw new RuntimeException('Closed opportunity accepted');
$db->exec("UPDATE office_crm_opportunities SET stage='proposal',status='open' WHERE id=11");
if(!$redeemer->redeem($next,$hash,7,9,$now,$allow))throw new RuntimeException('Failed redemption consumed the grant');
echo "CRM_CALC_LAUNCH_REDEEMER=PASS\n";
