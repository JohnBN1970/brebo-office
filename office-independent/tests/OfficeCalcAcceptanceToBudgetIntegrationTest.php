<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcAcceptanceSnapshotWriter.php';
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmTransferService.php';
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcPersistedAcceptanceVerifier.php';

use Brebo\Office\Calculation\OfficeCalcAcceptanceSnapshotWriter;
use Brebo\Office\Calculation\OfficeCalcCrmTransferService;
use Brebo\Office\Calculation\OfficeCalcPersistedAcceptanceVerifier;

if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
    fwrite(STDERR,"SQLite PDO required for end-to-end regression\n"); exit(1);
}
class HandoffIntegrationPdo extends PDO {
    public function prepare(string $query,array $options=[]): PDOStatement|false {
        return parent::prepare(str_replace(' FOR UPDATE','',$query),$options);
    }
}
$db=new HandoffIntegrationPdo('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys=ON');
foreach ([
 'CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,stage TEXT,status TEXT)',
 'CREATE TABLE office_crm_calc_acceptances(opportunity_id INTEGER PRIMARY KEY,calculation_id INTEGER,accepted_revision TEXT,status TEXT,accepted_by INTEGER)',
 'CREATE TABLE office_crm_calc_accepted_lines(opportunity_id INTEGER,source_line_id TEXT,cost_type TEXT,quantity INTEGER,unit_cost_cents INTEGER,activity_ref TEXT,PRIMARY KEY(opportunity_id,source_line_id))',
 'CREATE TABLE office_projects(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT)',
 'CREATE TABLE office_calc_crm_acceptance_transfers(id INTEGER PRIMARY KEY AUTOINCREMENT,crm_opportunity_id TEXT UNIQUE,acceptance_key TEXT UNIQUE,calculation_id INTEGER,accepted_revision TEXT,office_project_id INTEGER UNIQUE)',
 'CREATE TABLE office_calc_work_budget_baselines(id INTEGER PRIMARY KEY AUTOINCREMENT,acceptance_transfer_id INTEGER,version_number INTEGER,locked_at TEXT DEFAULT CURRENT_TIMESTAMP)',
 'CREATE TABLE office_calc_work_budget_lines(id INTEGER PRIMARY KEY AUTOINCREMENT,baseline_id INTEGER,source_line_id TEXT,cost_type TEXT,quantity INTEGER,unit_cost_cents INTEGER,budget_cents INTEGER,activity_ref TEXT)',
] as $sql) $db->exec($sql);
$db->exec("INSERT INTO office_crm_opportunities VALUES(11,'won','closed')");
$lines=[['line_id'=>'actual-1','cost_type'=>'labor','quantity'=>2,'unit_cost_cents'=>350,'activity_ref'=>'phase-a']];
$canonical=static fn(PDO $db,int $opp,int $calc,string $rev,int $actor):array =>
 ['verified'=>true,'calculation_id'=>$calc,'revision'=>$rev,'lines'=>$lines];
(new OfficeCalcAcceptanceSnapshotWriter($db))->accept(11,400,'rev-4',7,$canonical);
$acceptance=['crm_opportunity_id'=>'11','calculation_id'=>400,'accepted_revision'=>'rev-4','status'=>'accepted'];
$create=static function(PDO $db,array $acceptance):int {
 $q=$db->prepare('INSERT INTO office_projects(title) VALUES (?)');
 $q->execute(['CRM '.$acceptance['crm_opportunity_id']]);
 return (int)$db->lastInsertId();
};
$transfer=new OfficeCalcCrmTransferService($db);
$result=$transfer->transfer($acceptance,$lines,$create,new OfficeCalcPersistedAcceptanceVerifier());
if ($result['project_id']!==1 || $result['work_budget_id']!==1) throw new RuntimeException('Transfer identifiers invalid');
$stored=$db->query('SELECT l.budget_cents,b.locked_at,t.office_project_id FROM office_calc_work_budget_lines l JOIN office_calc_work_budget_baselines b ON b.id=l.baseline_id JOIN office_calc_crm_acceptance_transfers t ON t.id=b.acceptance_transfer_id')->fetch(PDO::FETCH_ASSOC);
if (!$stored || (int)$stored['budget_cents']!==700 || (int)$stored['office_project_id']!==1 || !$stored['locked_at']) throw new RuntimeException('Persisted baseline mismatch');
$failed=false;
try {$transfer->transfer($acceptance,$lines,$create,new OfficeCalcPersistedAcceptanceVerifier());}
catch(Throwable){$failed=true;}
if (!$failed || (int)$db->query('SELECT COUNT(*) FROM office_projects')->fetchColumn()!==1) throw new RuntimeException('Duplicate created a project');
echo "CRM_CALC_ACCEPTANCE_TO_LOCKED_BUDGET=PASS\n";
