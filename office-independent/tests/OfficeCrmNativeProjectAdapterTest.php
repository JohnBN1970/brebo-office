<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmNativeProjectAdapter.php';

use Brebo\Office\Crm\OfficeCrmNativeProjectAdapter;

if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) throw new RuntimeException('pdo_sqlite missing');

function adapterFixture(bool $codeColumn = false, bool $unmapped = false): PDO {
    $db = new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('CREATE TABLE office_operator_accounts (id INTEGER PRIMARY KEY, active INTEGER NOT NULL);
        CREATE TABLE office_module_permissions (operator_id INTEGER, module TEXT, permission TEXT);
        CREATE TABLE office_project_memberships (project_id INTEGER, operator_id INTEGER, permission TEXT);
        INSERT INTO office_operator_accounts VALUES (7,1),(8,0);');
    $extra = ($codeColumn ? ', code VARCHAR(40) NOT NULL' : '')
        .($unmapped ? ', external_ref TEXT NOT NULL' : '');
    $db->exec("CREATE TABLE office_projects (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(255) NOT NULL, status TEXT NOT NULL{$extra})");
    return $db;
}
function mustFail(callable $fn,string $label): void {
    try { $fn(); } catch (DomainException|RuntimeException|PDOException $e) { return; }
    throw new RuntimeException('Expected failure: '.$label);
}
$opportunity=['id'=>41,'title'=>'FICTIEF - directe opdracht'];
$db=adapterFixture();
$adapter=new OfficeCrmNativeProjectAdapter(7);
mustFail(fn()=>$adapter($db,$opportunity),'outside transaction');
$db->beginTransaction();
mustFail(fn()=>$adapter($db,$opportunity),'permission missing');
$db->rollBack();
$db->exec("INSERT INTO office_module_permissions VALUES (7,'projects','create'),(8,'projects','create')");
$db->beginTransaction();
mustFail(fn()=>(new OfficeCrmNativeProjectAdapter(8))($db,$opportunity),'inactive operator');
$id=$adapter($db,$opportunity);
if ($id!==1) throw new RuntimeException('Project was not created');
$project=$db->query('SELECT title,status FROM office_projects WHERE id=1')->fetch(PDO::FETCH_ASSOC);
if ($project !== ['title'=>'FICTIEF - directe opdracht','status'=>'preparation']) throw new RuntimeException('Project not in preparation');
$membership=$db->query("SELECT permission FROM office_project_memberships WHERE project_id=1 AND operator_id=7")->fetchColumn();
if ($membership!=='manage') throw new RuntimeException('Creator access missing');
$db->rollBack();
if ((int)$db->query('SELECT COUNT(*) FROM office_projects')->fetchColumn()!==0) throw new RuntimeException('Project creation was not transactional');
if ((int)$db->query('SELECT COUNT(*) FROM office_project_memberships')->fetchColumn()!==0) throw new RuntimeException('Membership creation was not transactional');

$db=adapterFixture(true);
$db->exec("INSERT INTO office_module_permissions VALUES (7,'projects','create')");
$db->beginTransaction();
$id=(new OfficeCrmNativeProjectAdapter(7))($db,$opportunity);
$code=$db->query('SELECT code FROM office_projects WHERE id='.$id)->fetchColumn();
if ($code !== 'CRM-DIRECT-41') throw new RuntimeException('Project code mismatch');
$db->rollBack();

$db=adapterFixture(false,true);
$db->exec("INSERT INTO office_module_permissions VALUES (7,'projects','create')");
$db->beginTransaction();
mustFail(fn()=>(new OfficeCrmNativeProjectAdapter(7))($db,$opportunity),'unsupported required project column');
$db->rollBack();
echo "CRM_NATIVE_PROJECT_ADAPTER=PASS\n";
