<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Quotation/OfficeQuotationCalcGenerationService.php';
require_once dirname(__DIR__).'/src/Quotation/OfficeQuotationBudgetHandoff.php';
use Brebo\Office\Quotation\OfficeQuotationCalcGenerationService;
use Brebo\Office\Quotation\OfficeQuotationBudgetHandoff;
function handoffAssert(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);}
class QuotationHandoffPdo extends PDO{public function prepare(string $query,array $options=[]):PDOStatement|false{return parent::prepare($this->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?str_replace(' FOR UPDATE','',$query):$query,$options);}}
$mysql=getenv('QUOTE_HANDOFF_MYSQL')==='1';
$db=$mysql?new QuotationHandoffPdo('mysql:host=127.0.0.1;dbname=office_quotation_handoff_test','root','handoff-test-only'):new QuotationHandoffPdo('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$auto=$mysql?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';$text='TEXT';
foreach([
 'CREATE TABLE office_operator_accounts(id VARCHAR(128) PRIMARY KEY,active INTEGER,can_edit_crm INTEGER)',
 'CREATE TABLE office_operator_sessions(token_hash VARCHAR(64) PRIMARY KEY,operator_id VARCHAR(128),expires_at DATETIME,revoked_at DATETIME)',
 'CREATE TABLE office_crm_relations(id INTEGER PRIMARY KEY,display_name VARCHAR(100),status VARCHAR(20))',
 'CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,relation_id INTEGER,revision INTEGER,stage VARCHAR(20),status VARCHAR(20),next_action_at DATETIME)',
 'CREATE TABLE office_calc_crm_drafts(id INTEGER PRIMARY KEY,opportunity_id INTEGER,crm_revision INTEGER,created_by INTEGER,project_id INTEGER)',
 'CREATE TABLE office_calc_crm_draft_collaborators(draft_id INTEGER,operator_id INTEGER)',
 'CREATE TABLE calculations(id INTEGER PRIMARY KEY,office_calculation_id INTEGER,office_project_id INTEGER,status VARCHAR(20))',
 "CREATE TABLE calculation_versions(id INTEGER PRIMARY KEY,calculation_id INTEGER,version_no INTEGER,direct_cost $text)",
 "CREATE TABLE calculation_lines(id INTEGER PRIMARY KEY,version_id INTEGER,sort_order INTEGER,line_type VARCHAR(32),quantity $text,labour_unit_cost $text,material_unit_cost $text,equipment_unit_cost $text,subcontracting_unit_cost $text,other_unit_cost $text)",
 "CREATE TABLE office_quotations(id $auto,relation_id INTEGER,opportunity_id INTEGER,calculation_id INTEGER,calculation_version INTEGER,quotation_type VARCHAR(24),title VARCHAR(255),status VARCHAR(24),currency VARCHAR(3) DEFAULT 'EUR',version INTEGER,amount_ex_vat_cents BIGINT,created_by INTEGER,approved_by INTEGER,approved_at DATETIME,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE office_quotation_sections(id $auto,quotation_id INTEGER,section_key VARCHAR(64),sort_order INTEGER,enabled INTEGER,body TEXT,provenance VARCHAR(64))",
 "CREATE TABLE office_quotation_versions(id $auto,quotation_id INTEGER,version INTEGER,snapshot_json TEXT,snapshot_sha256 VARCHAR(64),created_by INTEGER)",
 'CREATE TABLE office_quotation_budget_bindings(quotation_id INTEGER,quotation_version INTEGER,opportunity_id INTEGER,draft_id INTEGER,calculation_version INTEGER,source_hash VARCHAR(64),cost_json TEXT,cost_sha256 VARCHAR(64),created_by INTEGER,PRIMARY KEY(quotation_id,quotation_version))',
 "CREATE TABLE office_commercial_acceptances(id $auto,opportunity_id INTEGER UNIQUE,quotation_id INTEGER,acceptance_kind VARCHAR(24),evidence_document_id INTEGER,evidence_reference VARCHAR(255),accepted_by_operator_id INTEGER,accepted_at TIMESTAMP,contract_amount_ex_vat_cents BIGINT,idempotency_key VARCHAR(64) UNIQUE)",
 'CREATE TABLE office_crm_calc_acceptances(opportunity_id INTEGER PRIMARY KEY,calculation_id INTEGER,accepted_revision VARCHAR(191),status VARCHAR(16),accepted_by INTEGER)',
 "CREATE TABLE office_crm_calc_accepted_lines(opportunity_id INTEGER,source_line_id VARCHAR(191),cost_type VARCHAR(20),quantity $text,unit_cost_cents $text,activity_ref VARCHAR(191),PRIMARY KEY(opportunity_id,source_line_id))",
 "CREATE TABLE office_projects(id $auto,code VARCHAR(128) UNIQUE,title VARCHAR(255),status VARCHAR(32),project_kind VARCHAR(32),description TEXT)",
 'CREATE TABLE office_project_memberships(project_id INTEGER,operator_id INTEGER,permission VARCHAR(20),PRIMARY KEY(project_id,operator_id))',
 "CREATE TABLE office_calc_crm_acceptance_transfers(id $auto,crm_opportunity_id VARCHAR(191) UNIQUE,acceptance_key VARCHAR(64) UNIQUE,calculation_id INTEGER,accepted_revision VARCHAR(191),office_project_id INTEGER UNIQUE)",
 "CREATE TABLE office_calc_work_budget_baselines(id $auto,acceptance_transfer_id INTEGER,version_number INTEGER,locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE office_calc_work_budget_lines(id $auto,baseline_id INTEGER,source_line_id VARCHAR(191),cost_type VARCHAR(20),quantity $text,unit_cost_cents $text,budget_cents BIGINT,activity_ref VARCHAR(191))",
 'CREATE TABLE office_document_originals(id INTEGER PRIMARY KEY,sha256 VARCHAR(64),byte_size INTEGER)',
 'CREATE TABLE office_documents(id INTEGER PRIMARY KEY,original_id INTEGER,lifecycle_status VARCHAR(32))',
 'CREATE TABLE office_document_contexts(document_id INTEGER,context_type VARCHAR(32),context_id VARCHAR(100),relation_role VARCHAR(32))',
]as$sql)$db->exec($sql);
if($mysql)foreach(['office_crm_calc_accepted_lines','office_calc_work_budget_lines']as$t)$db->exec("ALTER TABLE $t MODIFY quantity DECIMAL(24,4),MODIFY unit_cost_cents DECIMAL(22,2)");
$db->exec("INSERT INTO office_operator_accounts VALUES('7',1,1),('8',1,1),('9',1,1)");$db->exec("INSERT INTO office_crm_relations VALUES(10,'Fixture relation','active')");$db->exec("INSERT INTO office_crm_opportunities VALUES(11,10,1,'proposal','open',NULL)");$db->exec('INSERT INTO office_calc_crm_drafts VALUES(1,11,1,7,NULL)');$db->exec('INSERT INTO office_calc_crm_draft_collaborators VALUES(1,8)');$db->exec("INSERT INTO calculations VALUES(21,1,NULL,'draft')");$db->exec("INSERT INTO calculation_versions VALUES(31,21,1,'30.8640')");$db->exec("INSERT INTO calculation_lines VALUES(41,31,1,'item','2.5000','0.0000','12.3456','0.0000','0.0000','0.0000')");
$db->exec("INSERT INTO office_document_originals VALUES(50,'".str_repeat('a',64)."',100)");$db->exec("INSERT INTO office_documents VALUES(51,50,'active')");$db->exec("INSERT INTO office_document_contexts VALUES(51,'organization','10','contractual')");
$source=['relation_id'=>10,'opportunity_id'=>11,'title'=>'Frozen quotation','sales_snapshot'=>['calculation_id'=>1,'version'=>1,'sales_lines'=>[['rubric'=>'Materiaal','description'=>'Kozijn','quantity'=>'2.5000','unit'=>'m2','amount_ex_vat_cents'=>5000]],'total_ex_vat_cents'=>5000]];
$resolver=static fn(int $actor,int $id,int $v):array=>$source;
putenv('OFFICE_QUOTATION_COST_BINDING_ENABLED=enabled');
$id=(new OfficeQuotationCalcGenerationService($db))->generate(7,['action'=>'generate_quotation','csrf'=>str_repeat('c',64),'calculation_id'=>1,'calculation_version'=>1],$resolver);
$hash=(string)$db->query('SELECT cost_sha256 FROM office_quotation_budget_bindings')->fetchColumn();handoffAssert(strlen($hash)===64,'Binding missing');
$handoff=new OfficeQuotationBudgetHandoff($db);
$reject=static function(callable $action,string $name)use($db):void{$denied=false;try{$action();}catch(Throwable $e){$denied=true;}handoffAssert($denied,$name.' accepted');handoffAssert((int)$db->query('SELECT COUNT(*) FROM office_projects')->fetchColumn()===0,$name.' created project');handoffAssert((int)$db->query('SELECT COUNT(*) FROM office_commercial_acceptances')->fetchColumn()===0,$name.' left commercial evidence');handoffAssert((int)$db->query('SELECT COUNT(*) FROM office_crm_calc_acceptances')->fetchColumn()===0,$name.' left Calc acceptance');};
$reject(fn()=>$handoff->transfer($id,7,1,$hash,51,true),'Draft status');
$db->exec("UPDATE office_quotations SET status='sent',approved_by=8,approved_at=CURRENT_TIMESTAMP");
$reject(fn()=>$handoff->transfer($id,9,1,$hash,51,true),'Unauthorized operator');
$reject(fn()=>$handoff->transfer($id,7,2,$hash,51,true),'Stale version');
$reject(fn()=>$handoff->transfer($id,7,1,str_repeat('0',64),51,true),'Changed cost hash');
$reject(fn()=>$handoff->transfer($id,7,1,$hash,null,true),'Missing evidence');
$reject(fn()=>$handoff->transfer($id,7,1,$hash,51,false),'Missing confirmation');
$db->exec("UPDATE office_document_contexts SET context_id='99'");$reject(fn()=>$handoff->transfer($id,7,1,$hash,51,true),'Wrong dossier');$db->exec("UPDATE office_document_contexts SET context_id='10'");
$db->exec('UPDATE office_operator_accounts SET active=0 WHERE id=7');$reject(fn()=>$handoff->transfer($id,7,1,$hash,51,true),'Revoked actor');$db->exec('UPDATE office_operator_accounts SET active=1 WHERE id=7');
if($mysql)$db->exec("CREATE TRIGGER fail_project BEFORE INSERT ON office_projects FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture_failure'");else $db->exec("CREATE TRIGGER fail_project BEFORE INSERT ON office_projects BEGIN SELECT RAISE(ABORT,'fixture_failure'); END");
$reject(fn()=>$handoff->transfer($id,7,1,$hash,51,true),'Project rollback');$db->exec('DROP TRIGGER fail_project');handoffAssert($db->query('SELECT status FROM office_quotations')->fetchColumn()==='sent','Quote acceptance was not rolled back');handoffAssert($db->query('SELECT status FROM office_crm_opportunities')->fetchColumn()==='open','Opportunity acceptance was not rolled back');
// Native draft changes after publication must never reprice the fixed quote budget.
$db->exec("UPDATE calculation_lines SET quantity='9.0000',material_unit_cost='99.0000'");
if(($argv[1]??'')==='--http'){
 require __DIR__.'/fixtures/quotation-handoff-http.php';
}else{
 $result=$handoff->transfer($id,7,1,$hash,51,true);$again=$handoff->transfer($id,7,1,$hash,null,true);handoffAssert($again['replayed']===true&&$again['project_id']===$result['project_id'],'Idempotent replay failed');
}
$row=$db->query('SELECT quantity,unit_cost_cents,budget_cents FROM office_calc_work_budget_lines')->fetch(PDO::FETCH_ASSOC);handoffAssert($row&&$row['quantity']==='2.5000'&&$row['unit_cost_cents']==='1234.56'&&(int)$row['budget_cents']===3086,'Fixed source changed');handoffAssert($db->query('SELECT status FROM office_quotations')->fetchColumn()==='accepted','Quotation not accepted');handoffAssert((int)$db->query('SELECT COUNT(*) FROM office_projects')->fetchColumn()===1,'Duplicate project');
$db->exec('UPDATE office_calc_work_budget_lines SET budget_cents=1');$denied=false;try{$handoff->transfer($id,7,1,$hash,null,true);}catch(DomainException){$denied=true;}handoffAssert($denied,'Changed persisted baseline replay accepted');
echo 'QUOTATION_BUDGET_'.($mysql?'MYSQL':'SQLITE')."=PASS\n";
