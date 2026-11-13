<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmDraftAccess.php';
use Brebo\Office\Calculation\OfficeCalcCrmDraftAccess;
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {fwrite(STDERR,"SQLite required\n");exit(1);}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_operator_accounts(id INTEGER PRIMARY KEY,active INTEGER)');
$db->exec('CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,revision INTEGER,stage TEXT,status TEXT)');
$db->exec('CREATE TABLE office_calc_crm_drafts(id INTEGER PRIMARY KEY,opportunity_id INTEGER,crm_revision INTEGER,project_id INTEGER)');
$db->exec("INSERT INTO office_operator_accounts VALUES(7,1),(8,0),(9,1)");
$db->exec("INSERT INTO office_crm_opportunities VALUES(11,4,'proposal','open')");
$db->exec('INSERT INTO office_calc_crm_drafts VALUES(1,11,4,NULL)');
$access=new OfficeCalcCrmDraftAccess($db);
$rights=static fn(PDO $db,int $actor,int $opp):bool=>$actor===7 && $opp===11;
if(!$access->canManage(7,1,$rights))throw new RuntimeException('Valid CRM access rejected');
if($access->canManage(8,1,$rights) || $access->canManage(9,1,$rights) || $access->canManage(7,2,$rights))throw new RuntimeException('Unauthorized access');
$db->exec('UPDATE office_crm_opportunities SET revision=5 WHERE id=11');
if($access->canManage(7,1,$rights))throw new RuntimeException('Stale revision permitted');
$db->exec('UPDATE office_crm_opportunities SET revision=4 WHERE id=11');
$db->exec('UPDATE office_calc_crm_drafts SET project_id=99 WHERE id=1');
if($access->canManage(7,1,$rights))throw new RuntimeException('Transferred draft permitted');
$db->exec('UPDATE office_calc_crm_drafts SET project_id=NULL WHERE id=1');
$db->exec("UPDATE office_crm_opportunities SET stage='won',status='closed' WHERE id=11");
if($access->canManage(7,1,$rights))throw new RuntimeException('Closed opportunity permitted');
echo "CRM_CALC_DRAFT_ACCESS=PASS\n";
