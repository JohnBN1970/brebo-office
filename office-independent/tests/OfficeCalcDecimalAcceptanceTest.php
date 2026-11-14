<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcAcceptanceSnapshotWriter.php';
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmTransferService.php';
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcPersistedAcceptanceVerifier.php';
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcWorkBudgetHandoff.php';
use Brebo\Office\Calculation\OfficeCalcAcceptanceSnapshotWriter;
use Brebo\Office\Calculation\OfficeCalcCrmTransferService;
use Brebo\Office\Calculation\OfficeCalcPersistedAcceptanceVerifier;
use Brebo\Office\Calculation\OfficeCalcWorkBudgetHandoff;

function check(bool $value, string $message): void {
    if (!$value) throw new RuntimeException($message);
}
function rejected(callable $action): void {
    try { $action(); } catch (Throwable $e) { return; }
    throw new RuntimeException('Invalid or lossy decimal operation succeeded');
}
class DecimalAcceptancePdo extends PDO {
    public function prepare(string $query, array $options=[]): PDOStatement|false {
        return parent::prepare($this->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'
            ? str_replace(' FOR UPDATE','',$query) : $query, $options);
    }
}
$mysql = getenv('CALC_DECIMAL_MYSQL') === '1';
$db = $mysql ? new DecimalAcceptancePdo('mysql:host=127.0.0.1;dbname=office_calc_decimal_test', 'root', 'decimal-test-only')
    : new DecimalAcceptancePdo('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// Dedicated disposable test DB only. TEXT keeps SQLite from converting decimal strings to floats.
$auto = $mysql ? 'INTEGER PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
$amount = $mysql ? 'BIGINT UNSIGNED' : 'TEXT';
foreach ([
 'CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,stage VARCHAR(20),status VARCHAR(20))',
 'CREATE TABLE office_crm_calc_acceptances(opportunity_id INTEGER PRIMARY KEY,calculation_id INTEGER,accepted_revision VARCHAR(191),status VARCHAR(20),accepted_by INTEGER)',
 "CREATE TABLE office_crm_calc_accepted_lines(opportunity_id INTEGER,source_line_id VARCHAR(191),cost_type VARCHAR(20),quantity $amount,unit_cost_cents $amount,activity_ref VARCHAR(191),PRIMARY KEY(opportunity_id,source_line_id))",
 "CREATE TABLE office_projects(id $auto,title VARCHAR(191))",
 "CREATE TABLE office_calc_crm_acceptance_transfers(id $auto,crm_opportunity_id VARCHAR(191) UNIQUE,acceptance_key VARCHAR(64) UNIQUE,calculation_id INTEGER,accepted_revision VARCHAR(191),office_project_id INTEGER UNIQUE)",
 "CREATE TABLE office_calc_work_budget_baselines(id $auto,acceptance_transfer_id INTEGER,version_number INTEGER,locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE office_calc_work_budget_lines(id $auto,baseline_id INTEGER,source_line_id VARCHAR(191),cost_type VARCHAR(20),quantity $amount,unit_cost_cents $amount,budget_cents BIGINT,activity_ref VARCHAR(191))",
] as $sql) $db->exec($sql);
$db->exec("INSERT INTO office_crm_opportunities VALUES(11,'won','closed'),(12,'won','closed')");
$acceptance=['crm_opportunity_id'=>'11','calculation_id'=>400,'accepted_revision'=>'rev-exact','status'=>'accepted'];
$line=['line_id'=>'fractional','cost_type'=>'material','quantity'=>2,'unit_cost_cents'=>1235,
    'quantity_decimal'=>'2.5000','unit_cost_decimal'=>'12.3456','activity_ref'=>'phase-a'];
$lines=[$line];
$source=static fn(PDO $db,int $opp,int $calc,string $rev,int $actor):array =>
 ['verified'=>true,'calculation_id'=>$calc,'revision'=>$rev,'lines'=>$lines];
$writer=new OfficeCalcAcceptanceSnapshotWriter($db);
if ($mysql) {
    // Old integer columns must roll back rather than commit a rounded acceptance.
    rejected(fn()=>$writer->accept(11,400,'rev-exact',7,$source));
    check((int)$db->query('SELECT COUNT(*) FROM office_crm_calc_acceptances')->fetchColumn()===0,'Old schema acceptance was not rolled back');
    $db->exec(file_get_contents(dirname(__DIR__).'/deployment/hostinger/sql/office-calc-acceptance-decimals.sql'));
}
$writer->accept(11,400,'rev-exact',7,$source);
$create=static function(PDO $db,array $acceptance):int {
    $db->exec("INSERT INTO office_projects(title) VALUES('Decimal project')");
    return (int)$db->lastInsertId();
};
$transfer=new OfficeCalcCrmTransferService($db);
$verify=new OfficeCalcPersistedAcceptanceVerifier();
$changed=$lines; $changed[0]['unit_cost_decimal']='12.3457';
rejected(fn()=>$transfer->transfer($acceptance,$changed,$create,$verify));
check((int)$db->query('SELECT COUNT(*) FROM office_projects')->fetchColumn()===0,'Price mismatch created a project');
// Reject a legacy integer projection of this exact accepted source.
$legacy=$line; unset($legacy['quantity_decimal'],$legacy['unit_cost_decimal']);
rejected(fn()=>$transfer->transfer($acceptance,[$legacy],$create,$verify));
$result=$transfer->transfer($acceptance,$lines,$create,$verify);
$row=$db->query('SELECT quantity,unit_cost_cents,budget_cents FROM office_calc_work_budget_lines')->fetch(PDO::FETCH_ASSOC);
check($row && (string)$row['quantity']==='2.5000' && (string)$row['unit_cost_cents']==='1234.56'
    && (int)$row['budget_cents']===3086,'Exact persisted budget differs');
check($result['project_id']===1,'Invalid project');
rejected(fn()=>$transfer->transfer($acceptance,$lines,$create,$verify));
check((int)$db->query('SELECT COUNT(*) FROM office_projects')->fetchColumn()===1,'Duplicate created another project');
// A database that silently rounds either snapshot or baseline must roll back.
if (!$mysql) {
    $db->exec("CREATE TRIGGER truncate_acceptance AFTER INSERT ON office_crm_calc_accepted_lines BEGIN UPDATE office_crm_calc_accepted_lines SET quantity='2' WHERE opportunity_id=NEW.opportunity_id; END");
    rejected(fn()=>$writer->accept(12,401,'rev-truncate',7,$source));
    check((int)$db->query('SELECT COUNT(*) FROM office_crm_calc_acceptances WHERE opportunity_id=12')->fetchColumn()===0,'Truncated snapshot survived');
    $db->exec('DROP TRIGGER truncate_acceptance');
    $writer->accept(12,401,'rev-truncate',7,$source);
    $db->exec("CREATE TRIGGER truncate_budget AFTER INSERT ON office_calc_work_budget_lines BEGIN UPDATE office_calc_work_budget_lines SET unit_cost_cents='1234' WHERE id=NEW.id; END");
    $second=['crm_opportunity_id'=>'12','calculation_id'=>401,'accepted_revision'=>'rev-truncate','status'=>'accepted'];
    rejected(fn()=>$transfer->transfer($second,$lines,$create,$verify));
    check((int)$db->query('SELECT COUNT(*) FROM office_projects')->fetchColumn()===1,'Truncated budget project survived');
}
foreach ([['0.0050','1.0000',1],['0.0049','1.0000',0],['12.3456','2.5000',3086]] as [$price,$quantity,$expected]) {
    $test=$line; $test['quantity_decimal']=$quantity; $test['unit_cost_decimal']=$price;
    check(OfficeCalcWorkBudgetHandoff::prepare($acceptance,[$test])['items'][0]['budget_cents']===$expected,'Line rounding mismatch');
}
foreach ([['quantity_decimal'=>'2.50001'],['unit_cost_decimal'=>'1e2'],['quantity_decimal'=>2.5],['unit_cost_decimal'=>'-1'],['quantity_decimal'=>'999999999999999','unit_cost_decimal'=>'999999999999999']] as $invalid) {
    rejected(fn()=>OfficeCalcWorkBudgetHandoff::prepare($acceptance,[array_replace($line,$invalid)]));
}
$missing=$line; unset($missing['unit_cost_decimal']);
rejected(fn()=>OfficeCalcWorkBudgetHandoff::prepare($acceptance,[$missing]));
if ($mysql) {
    require_once dirname(__DIR__).'/deployment/hostinger/probe-calc-acceptance.php';
    probeCalcAcceptance($db, dirname(__DIR__));
}
echo 'CRM_CALC_DECIMAL_ACCEPTANCE_'.($mysql?'MYSQL':'SQLITE')."=PASS\n";
