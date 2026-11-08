<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmDraftIdentity.php';
use Brebo\Office\Calculation\OfficeCalcCrmDraftIdentity;
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {fwrite(STDERR,"SQLite required\n");exit(1);}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,revision INTEGER,stage TEXT,status TEXT)');
$db->exec('CREATE TABLE office_calc_crm_drafts(id INTEGER PRIMARY KEY AUTOINCREMENT,opportunity_id INTEGER UNIQUE,crm_revision INTEGER,created_by INTEGER,project_id INTEGER)');
$db->exec("INSERT INTO office_crm_opportunities VALUES (1,4,'proposal','open'),(2,1,'won','closed')");
$draft=new OfficeCalcCrmDraftIdentity($db);
$allow=static fn(PDO $db,int $actor,int $opp):bool=>$actor===7;
$reject=static function(callable $fn):void{$failed=false;try{$fn();}catch(Throwable){$failed=true;}if(!$failed)throw new RuntimeException('Expected rejection');};
$id=$draft->findOrCreate(1,4,7,$allow);
if($id!==1 || $draft->findOrCreate(1,4,7,$allow)!==1)throw new RuntimeException('Idempotency failed');
$reject(static fn()=> $draft->findOrCreate(1,4,8,$allow));
$reject(static fn()=> $draft->findOrCreate(1,3,7,$allow));
$reject(static fn()=> $draft->findOrCreate(2,1,7,$allow));
$db->exec('UPDATE office_calc_crm_drafts SET project_id=20 WHERE id=1');
$reject(static fn()=> $draft->findOrCreate(1,4,7,$allow));
if((int)$db->query('SELECT COUNT(*) FROM office_calc_crm_drafts')->fetchColumn()!==1)throw new RuntimeException('Unexpected draft');
echo "CRM_CALC_DRAFT_IDENTITY=PASS\n";
