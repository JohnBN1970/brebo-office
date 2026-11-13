<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmLaunchIssuer.php';
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmLaunchRedeemer.php';
use Brebo\Office\Calculation\OfficeCalcCrmLaunchIssuer;
use Brebo\Office\Calculation\OfficeCalcCrmLaunchRedeemer;
if(!in_array('sqlite',PDO::getAvailableDrivers(),true)){fwrite(STDERR,"SQLite required\n");exit(1);}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach([
 'CREATE TABLE office_operator_accounts(id INTEGER PRIMARY KEY,active INTEGER,can_edit_crm INTEGER)',
 'CREATE TABLE office_operator_sessions(token_hash TEXT PRIMARY KEY,operator_id TEXT,revoked_at TEXT,expires_at TEXT)',
 'CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,revision INTEGER,stage TEXT,status TEXT)',
 'CREATE TABLE office_calc_crm_drafts(id INTEGER PRIMARY KEY,opportunity_id INTEGER,crm_revision INTEGER,project_id INTEGER)',
 'CREATE TABLE office_calc_crm_launch_grants(reference TEXT PRIMARY KEY,session_hash TEXT,actor_id INTEGER,draft_id INTEGER,expires_at TEXT,consumed_at TEXT,revoked_at TEXT)',
] as $sql)$db->exec($sql);
$token=str_repeat('s',40);$hash=hash('sha256',$token);$csrf=str_repeat('c',32);$now=1000;
$db->exec('INSERT INTO office_operator_accounts VALUES(7,1,1)');
$db->prepare('INSERT INTO office_operator_sessions VALUES(?,?,NULL,?)')->execute([$hash,'7',gmdate('Y-m-d H:i:s',2000)]);
$db->exec("INSERT INTO office_crm_opportunities VALUES(11,4,'proposal','open')");
$db->exec('INSERT INTO office_calc_crm_drafts VALUES(9,11,4,NULL)');
$rights=static fn(PDO $db,int $actor,int $opp):bool=>$actor===7&&$opp===11;
$issuer=new OfficeCalcCrmLaunchIssuer($db);
$redeemer=new OfficeCalcCrmLaunchRedeemer($db);
if($issuer->issue($token,'bad',$csrf,9,$now,$rights)!==null)throw new RuntimeException('Bad CSRF issued grant');
$db->exec('UPDATE office_operator_accounts SET can_edit_crm=0 WHERE id=7');
if($issuer->issue($token,$csrf,$csrf,9,$now,$rights)!==null)throw new RuntimeException('Read-only CRM operator issued Calc grant');
$db->exec('UPDATE office_operator_accounts SET can_edit_crm=1 WHERE id=7');
$ref=$issuer->issue($token,$csrf,$csrf,9,$now,$rights);
if(!is_string($ref)||strlen($ref)!==64)throw new RuntimeException('Valid CRM launch not issued');
if(!$redeemer->redeem($ref,$hash,7,9,$now,$rights))throw new RuntimeException('Issued grant not redeemable');
if($redeemer->redeem($ref,$hash,7,9,$now,$rights))throw new RuntimeException('Launch replay permitted');
$issued=$issuer->issueSigned($token,$csrf,$csrf,9,$now,str_repeat('k',40),$rights);
if (!is_array($issued) || !preg_match('/^[a-f0-9]{64}$/D',(string)($issued['reference']??'')))
    throw new RuntimeException('Signed CRM launch reference missing');
if (!is_string($issued['token']??null)) throw new RuntimeException('Signed CRM launch credential missing');
$parts=explode('.',$issued['token']);
if (count($parts)!==2 || !hash_equals(hash_hmac('sha256',$parts[0],str_repeat('k',40)),$parts[1]))
    throw new RuntimeException('Signed CRM launch signature incorrect');
$payload=json_decode(base64_decode(strtr($parts[0],'-_','+/'),true),true,512,JSON_THROW_ON_ERROR);
if (!is_array($payload) || $payload['v']!==2 || $payload['project_id']!==null
    || $payload['grant_ref']!==$issued['reference'] || $payload['calculation_id']!==9)
    throw new RuntimeException('Signed CRM launch payload incorrect');
$db->exec('UPDATE office_operator_accounts SET can_edit_crm=0 WHERE id=7');
if ($issuer->issueSigned($token,$csrf,$csrf,9,$now,str_repeat('k',40),$rights)!==null)
    throw new RuntimeException('Signed launch allowed revoked CRM rights');
echo "CRM_CALC_LAUNCH_ISSUE_REDEEM=PASS\n";
