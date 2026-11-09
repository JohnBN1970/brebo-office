<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmDraftLineRepository.php';
use Brebo\Office\Calculation\OfficeCalcCrmDraftLineRepository;
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) { echo "SQLite unavailable\n"; exit(1); }
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach ([
 'CREATE TABLE office_operator_accounts(id INTEGER PRIMARY KEY,active INTEGER)',
 'CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,revision INTEGER,stage TEXT,status TEXT)',
 'CREATE TABLE office_calc_crm_drafts(id INTEGER PRIMARY KEY,opportunity_id INTEGER,crm_revision INTEGER,project_id INTEGER)',
 'CREATE TABLE office_calc_crm_draft_lines(draft_id INTEGER,line_id TEXT,cost_type TEXT,description TEXT,quantity INTEGER,unit_cost_cents INTEGER,activity_ref TEXT,updated_by INTEGER,PRIMARY KEY(draft_id,line_id))'
] as $sql) $db->exec($sql);
$db->exec('INSERT INTO office_operator_accounts VALUES(7,1)');
$db->exec("INSERT INTO office_crm_opportunities VALUES(11,4,'proposal','open')");
$db->exec('INSERT INTO office_calc_crm_drafts VALUES(9,11,4,NULL)');
$rights=static fn(PDO $db,int $actor,int $opp):bool=>$actor===7 && $opp===11;
$repo=new OfficeCalcCrmDraftLineRepository($db);
$repo->save(9,7,'line_1','labor','Montage',2,4500,null,$rights);
$repo->save(9,7,'line_1','labor','Montageuren',3,4600,'A1',$rights);
$lines=$repo->list(9,7,$rights);
if(count($lines)!==1 || $lines[0]['quantity']!=3 || $lines[0]['unit_cost_cents']!=4600)throw new RuntimeException('Draft lines not persisted');
$db->exec('UPDATE office_crm_opportunities SET revision=5 WHERE id=11');
try{$repo->save(9,7,'line_2','material','Glas',1,2000,null,$rights);throw new RuntimeException('Stale draft writable');}
catch(DomainException $expected){}
if(count($db->query('SELECT * FROM office_calc_crm_draft_lines')->fetchAll())!==1)throw new RuntimeException('Denied write persisted');
echo "CRM_CALC_DRAFT_LINES=PASS\n";
