<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Dashboard/OfficePersonalDashboard.php';
require_once dirname(__DIR__).'/src/Dashboard/OfficeDashboardKpis.php';
use Brebo\Office\Dashboard\OfficeDashboardKpis;
use Brebo\Office\Dashboard\OfficePersonalDashboard;

function must(bool $ok,string $message):void { if (!$ok) throw new RuntimeException($message); }
$root=dirname(__DIR__);
$router=file_get_contents($root.'/deployment/hostinger/office-test-index.php');
$home=file_get_contents($root.'/public/office-home.php');
$page=file_get_contents($root.'/public/office-personal-dashboard.php');
$provider=file_get_contents($root.'/src/Dashboard/OfficeDashboardKpis.php');
foreach ([$router,$home,$page,$provider] as $s) must(is_string($s), 'Missing source');
must(str_contains($router,"'/office-test/mijn-dashboard' => 'office-personal-dashboard.php'"),'Router missing');
must(str_contains($home,'/office-test/mijn-dashboard'),'Workspace link missing');
must(str_contains($page,'currentOperator()') && str_contains($page,'active=1'),'Session or account guard missing');
must(str_contains($page,'OFFICE_DASHBOARD_PREFERENCES_SCHEMA_VERIFIED'),'Migration gating missing');
must(str_contains($page,"hash_hmac('sha256'") && str_contains($page,'hash_equals($expected, $token)'),'CSRF protection missing');
must(!str_contains($page,"$_GET['operator_id']") && !str_contains($page,"$_POST['operator_id']"),'Client identity accepted');
must(str_contains($provider,'office_project_memberships') && str_contains($provider,"m.permission IN ('read','manage')"),'Project ACL missing');
must(!str_contains($provider,'OfficeInzetWeekDemo'),'Demo KPI prohibited');
must(OfficePersonalDashboard::layout(['finance','projects','projects'],['projects'])===['projects'],'Unauthorized widget not filtered');
must(OfficePersonalDashboard::render(['finance'],[])==='<section class="office-personal-dashboard" aria-label="Mijn dashboard"><div class="office-personal-kpis"></div></section>','Missing data must not fabricate KPI');
try { (new OfficeDashboardKpis(new PDO('sqlite::memory:')))->forOperator('0'); throw new RuntimeException('Bad operator accepted'); }
catch(RuntimeException $e) { must($e->getMessage()!=='Bad operator accepted','Operator guard missing'); }
if (in_array('sqlite',PDO::getAvailableDrivers(),true)) {
  $db=new PDO('sqlite::memory:');
  $db->exec("CREATE TABLE office_operator_accounts(id INTEGER PRIMARY KEY,active INTEGER);
    CREATE TABLE office_projects(id INTEGER PRIMARY KEY);
    CREATE TABLE office_project_memberships(operator_id INTEGER,project_id INTEGER,permission TEXT);
    INSERT INTO office_operator_accounts VALUES(17,1),(18,1),(19,0);
    INSERT INTO office_projects VALUES(1),(2),(3);
    INSERT INTO office_project_memberships VALUES(17,1,'read'),(17,2,'manage'),(18,3,'read'),(19,3,'read');");
  $kpi=new OfficeDashboardKpis($db);
  must($kpi->forOperator('17')['projects']['value']==='2','Project isolation broken');
  must($kpi->forOperator('18')['projects']['value']==='1','Operator overlap');
  must($kpi->forOperator('19')['projects']['value']==='0','Inactive operator leak');
  $db->exec("DELETE FROM office_project_memberships WHERE operator_id=17");
  must($kpi->forOperator('17')['projects']['value']==='0','Revocation not reflected');
  $db->exec('DROP TABLE office_project_memberships');
  try { $kpi->forOperator('17'); throw new RuntimeException('Missing data fabricated'); }
  catch(PDOException $e) {}
}
echo "Dashboard integration boundary checks passed\n";
