<?php
declare(strict_types=1);
foreach(['OfficePersonalDashboard','OfficeDashboardPreferences','OfficeDashboardCatalog','OfficeDashboardKpis'] as $c)require_once __DIR__.'/../src/Dashboard/'.$c.'.php';
require_once __DIR__.'/../src/Intake/OfficeTheme.php';
use Brebo\Office\Dashboard\OfficeDashboardCatalog as Catalog;
use Brebo\Office\Dashboard\OfficeDashboardPreferences as Preferences;
use Brebo\Office\Dashboard\OfficeDashboardKpis;
use Brebo\Office\Intake\OfficeTheme;
function checkSuite(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
checkSuite(Catalog::format('budget',null)==='Niet beschikbaar','Unknown budget became zero');
checkSuite(Catalog::format('budget',0)==='€ 0,00','Known zero lost');
checkSuite(Catalog::format('budget',-12345)==='-€ 123,45','Negative currency');
checkSuite(Catalog::format('approved',5400)==='1,50 uur','Seconds conversion');
checkSuite(array_keys(Catalog::visible(['projects'=>1,'budget'=>2,'debtors'=>3,'approved'=>4,'forged'=>5],true))===['projects'],'Monitor leaked private widget');
$boards=Preferences::normalizeBoards(['main'=>['name'=>'Test','columns'=>99,'widgets'=>['debtors','projects','projects']]],['projects']);
checkSuite($boards['main']['widgets']===['projects']&&$boards['main']['columns']===3,'Untrusted layout grants access');
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('SHA2',static fn($s,$bits)=>hash('sha256',$s),2);
$db->exec("CREATE TABLE office_dashboard_preferences(operator_id INTEGER PRIMARY KEY,layout_json TEXT); CREATE TABLE office_operator_accounts(id INTEGER PRIMARY KEY,active INTEGER); CREATE TABLE office_projects(id INTEGER PRIMARY KEY,title TEXT,status TEXT); CREATE TABLE office_project_memberships(operator_id INTEGER,project_id INTEGER,permission TEXT); INSERT INTO office_operator_accounts VALUES(17,1),(18,1); INSERT INTO office_projects VALUES(1,'Hidden name','active'),(2,'Other person','active'); INSERT INTO office_project_memberships VALUES(17,1,'read'),(18,2,'read');");
$p=new Preferences($db);$state=$p->boards('17',['projects']);
$p->saveBoard('17','main',['name'=>'My view','widgets'=>['projects'],'columns'=>2],['projects'],$state['revision']);
checkSuite($p->boards('17',['projects'])['boards']['main']['columns']===2,'Saved layout missing');
checkSuite($p->boards('18',['projects'])['boards']['main']['name']!=='My view','Other user layout leak');
try{$p->saveBoard('17','main',['widgets'=>['projects']],['projects'],$state['revision']);throw new RuntimeException('Stale write accepted');}catch(DomainException){}
$current=$p->boards('17',['projects']);$p->saveBoard('17','second',['name'=>'Second','widgets'=>['projects']],['projects'],$current['revision']);
checkSuite(count($p->boards('17',['projects'])['boards'])===2,'Multiple dashboards missing');
checkSuite($p->boards('17',[])['boards']['main']['widgets']===[],'Revoked widget retained');
$suite=(new OfficeDashboardKpis($db))->suite('17');
checkSuite($suite['widgets']['projects']['value']===1,'Portfolio scope');
checkSuite(count($suite['projects'])===1&&$suite['projects'][0]['id']===1,'Project leak');
checkSuite(!$suite['finance']&&!isset($suite['widgets']['debtors']),'Missing finance ACL grants access');
checkSuite($suite['widgets']['planned']['value']===null,'Missing planning became zero');
$db->exec('DELETE FROM office_project_memberships WHERE operator_id=17');
checkSuite((new OfficeDashboardKpis($db))->suite('17')['widgets']['projects']['value']===0,'Live revocation');
$policy=OfficeTheme::policy("default-src 'none'; script-src 'none'; script-src-elem 'none'; form-action 'self'");
checkSuite(str_contains($policy,"form-action 'self'")&&!str_contains($policy,"script-src 'none'")&&!str_contains($policy,"script-src 'unsafe-inline'"),'Theme CSP widened');
checkSuite(substr_count(OfficeTheme::controls(),'aria-pressed="false"')===3,'Three theme controls required');
echo "OFFICE_DASHBOARD_SUITE_PASS\n";
