<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmDirectProjectAcceptance.php';
use Brebo\Office\Crm\OfficeCrmDirectProjectAcceptance;

$db=new PDO('sqlite::memory:',null,null,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec(<<<'SQL'
CREATE TABLE office_operator_accounts(id INTEGER PRIMARY KEY,active INTEGER);
CREATE TABLE office_module_permissions(operator_id INTEGER,module TEXT,permission TEXT);
CREATE TABLE office_crm_relations(id INTEGER PRIMARY KEY);
CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,relation_id INTEGER,title TEXT,stage TEXT,status TEXT);
CREATE TABLE office_projects(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT NOT NULL,status TEXT NOT NULL);
CREATE TABLE office_project_memberships(project_id INTEGER,operator_id INTEGER,permission TEXT);
CREATE TABLE office_calc_crm_acceptance_transfers(crm_opportunity_id INTEGER,office_project_id INTEGER);
CREATE TABLE office_crm_direct_project_transfers(
    opportunity_id INTEGER PRIMARY KEY,
    project_id INTEGER UNIQUE NOT NULL,
    relation_id INTEGER NOT NULL,
    FOREIGN KEY (opportunity_id) REFERENCES office_crm_opportunities(id),
    FOREIGN KEY (project_id) REFERENCES office_projects(id),
    FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id)
);
INSERT INTO office_operator_accounts VALUES (7,1),(8,0),(9,1);
INSERT INTO office_module_permissions VALUES (7,'projects','create'),(8,'projects','create'),(9,'projects','create');
INSERT INTO office_crm_relations VALUES (5);
INSERT INTO office_crm_opportunities VALUES
    (101,5,'FICTIEF direct gegund','won','closed'),
    (102,5,'FICTIEF niet gegund','prospect','open'),
    (103,5,'FICTIEF via calc gegund','won','closed'),
    (104,5,'FICTIEF zonder bevoegdheid','won','closed');
INSERT INTO office_calc_crm_acceptance_transfers VALUES (103,77);
SQL
);
$accept=new OfficeCrmDirectProjectAcceptance($db);
function expectReject(callable $f,string $label): void {
    try { $f(); } catch (DomainException|RuntimeException|PDOException $e) { return; }
    throw new RuntimeException('Expected direct transfer rejection: '.$label);
}
expectReject(fn()=>$accept->transferForOperator(7,102),'not won');
expectReject(fn()=>$accept->transferForOperator(7,103),'existing calculation transfer');
expectReject(fn()=>$accept->transferForOperator(8,104),'inactive operator');
expectReject(fn()=>$accept->transferForOperator(99,104),'unprivileged operator');
$first=$accept->transferForOperator(7,101);
$second=$accept->transferForOperator(7,101);
expectReject(fn()=>$accept->transferForOperator(9,101),'authorized creator cannot view other project');
$db->exec("INSERT INTO office_project_memberships VALUES ({$first},9,'read')");
if ($accept->transferForOperator(9,101)!==$first) throw new RuntimeException('Read-only member cannot access idempotent handoff');
$db->exec("DELETE FROM office_project_memberships WHERE operator_id=9");
$db->exec("DELETE FROM office_project_memberships WHERE operator_id=7");
expectReject(fn()=>$accept->transferForOperator(7,101),'creator membership revoked');
$db->exec("INSERT INTO office_project_memberships VALUES ({$first},7,'manage')");
if ($accept->transferForOperator(7,101)!==$first) throw new RuntimeException('Restored creator access was not honored');
$db->exec('INSERT INTO office_calc_crm_acceptance_transfers VALUES (101,77)');
expectReject(fn()=>$accept->transferForOperator(7,101),'conflicting Calc mapping on repeat');
$db->exec('DELETE FROM office_calc_crm_acceptance_transfers WHERE crm_opportunity_id=101');
expectReject(fn()=>$accept->transferForOperator(8,101),'idempotent request by inactive actor');
expectReject(fn()=>$accept->transferForOperator(99,101),'idempotent request by unprivileged actor');
if ($first!==$second || $first<1) throw new RuntimeException('Direct transfer is not idempotent');
if ((int)$db->query('SELECT COUNT(*) FROM office_projects')->fetchColumn()!==1) throw new RuntimeException('Duplicate project created');
$project=$db->query('SELECT status FROM office_projects')->fetchColumn();
if ($project!=='preparation') throw new RuntimeException('Direct project incorrectly started execution');
if ((int)$db->query('SELECT COUNT(*) FROM office_project_memberships')->fetchColumn()!==1) throw new RuntimeException('Creator project access not persisted');
if ((int)$db->query('SELECT COUNT(*) FROM office_crm_direct_project_transfers')->fetchColumn()!==1) throw new RuntimeException('Mapping was not persisted');
echo "CRM_DIRECT_NATIVE_TRANSFER_ROUNDTRIP=PASS\n";
