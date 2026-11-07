<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Finance/OfficeFinanceAccess.php';
require dirname(__DIR__).'/src/Finance/OfficeFinanceSalesOverview.php';
require dirname(__DIR__).'/src/Intake/OfficeMount.php';
require dirname(__DIR__).'/src/Finance/OfficeFinanceBankPanel.php';
require dirname(__DIR__).'/src/Finance/OfficeFinanceView.php';
use Brebo\Office\Finance\OfficeFinanceSalesOverview as Overview;
use Brebo\Office\Finance\OfficeFinanceView;
function overviewCheck(bool $ok,string $message): void{if(!$ok)throw new RuntimeException($message);}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE office_operator_accounts(id INTEGER PRIMARY KEY,active INTEGER);INSERT INTO office_operator_accounts VALUES(1,1),(2,1),(3,0);
CREATE TABLE office_module_permissions(operator_id INTEGER,module TEXT,permission TEXT);INSERT INTO office_module_permissions VALUES(1,'finance','read'),(2,'finance','read'),(3,'finance','read');
CREATE TABLE office_project_memberships(project_id INTEGER,operator_id INTEGER,permission TEXT);INSERT INTO office_project_memberships VALUES(10,1,'read'),(10,1,'manage'),(20,2,'read');
CREATE TABLE office_finance_sales_invoice(id INTEGER PRIMARY KEY,office_project_id INTEGER,operator_id INTEGER,invoice_date TEXT,due_date TEXT,net_cents INTEGER,gross_cents INTEGER,paid_cents INTEGER,status TEXT,invoice_number TEXT);
INSERT INTO office_finance_sales_invoice VALUES
(1,10,1,'2026-10-01','2026-10-01',10000,12100,2100,'sent','1'),
(2,10,1,'2026-11-01','2026-11-07',20000,24200,24200,'sent','2'),
(3,NULL,1,'2025-12-01','2025-12-15',30000,36300,0,'sent','3'),
(4,20,2,'2026-10-01','2026-10-15',999999,999999,0,'sent','4'),
(5,NULL,2,'2026-10-01','2026-10-15',888888,888888,0,'sent','5'),
(6,10,1,'2026-11-07','2026-11-21',50000,60500,0,'draft',NULL),
(7,10,1,'2026-11-07','2026-11-21',700000,847000,0,'sending',NULL),
(8,10,1,'2026-11-07','2026-11-21',800000,968000,0,'reconciliation_required',NULL),
(9,10,1,'2026-11-10','2026-12-24',40000,48400,0,'sent','9');
CREATE TABLE office_project_contracts(id INTEGER PRIMARY KEY,office_project_id INTEGER,contract_amount TEXT,currency TEXT,status TEXT);INSERT INTO office_project_contracts VALUES(100,10,'1000.00','EUR','signed'),(200,20,'99999.00','EUR','signed');
CREATE TABLE office_finance_sales_context(invoice_id INTEGER,source_contract_id INTEGER,source_term_id INTEGER);INSERT INTO office_finance_sales_context VALUES(1,100,101),(2,100,102),(6,100,103);
CREATE TABLE office_project_terms(id INTEGER,contract_id INTEGER,status TEXT);INSERT INTO office_project_terms VALUES(101,100,'paid'),(102,100,'planned'),(103,100,'planned');");
$q=$db->prepare("INSERT INTO office_finance_sales_invoice VALUES(?,NULL,1,'2026-11-07','2026-11-21',100,121,0,'draft',NULL)");for($i=10;$i<260;$i++)$q->execute([$i]);
$writes=$db->query('SELECT total_changes()')->fetchColumn();$all=Overview::read($db,'1',null,'2026-11-07');
overviewCheck($all['ytd_net_cents']===30000,'YTD excludes drafts, unknown delivery and other years or future dates');
overviewCheck($all['outstanding_gross_cents']===94700&&$all['overdue_gross_cents']===46300,'Actual receipts deducted from issued amounts');
overviewCheck($all['receipts_by_days']===[30=>46300,60=>94700,90=>94700],'Cash horizons use due date and actual remaining receipts; drafts excluded');
overviewCheck($all['paid_gross_cents']===26300&&$all['draft_gross_cents']===90750&&$all['draft_count']===251,'All records counted beyond list limit');
overviewCheck($all['ageing']['31–60']===10000&&$all['ageing']['90+']===36300,'Ageing uses calendar days past due date');
overviewCheck($all['unbilled_net_cents']===70000&&$all['contracts_complete']===true,'Draft not subtracted from remaining contract work');
overviewCheck($db->query('SELECT total_changes()')->fetchColumn()===$writes,'Overview performs no writes');
$project=Overview::read($db,'1',10,'2026-11-07');overviewCheck($project['outstanding_gross_cents']===58400&&$project['draft_count']===1,'Project scope excludes private and other project invoices');
foreach([['1',20],['3',null]] as [$operator,$scope]){try{Overview::read($db,$operator,$scope,'2026-11-07');throw new RuntimeException('Unauthorized totals');}catch(DomainException){}}
$db->exec("UPDATE office_project_terms SET status='invoiced' WHERE id=103;DELETE FROM office_finance_sales_context WHERE invoice_id=6");
$partial=Overview::read($db,'1',null,'2026-11-07');overviewCheck($partial['unbilled_net_cents']===null&&!$partial['contracts_complete'],'Unlinked historical terms do not become invented totals');
putenv('OFFICE_BASE_PATH=/office-test');$keys=array_keys(OfficeFinanceView::QUEUES);$data=['counts'=>array_fill_keys($keys,0),'amounts_inc_vat'=>array_fill_keys($keys,'0.00'),'queues'=>array_fill_keys($keys,[]),'generated_at'=>1,'sales_overview'=>$all];
$html=OfficeFinanceView::render($data,'dashboard');foreach(['Openstaande verkoopfacturen','€ 300,00','€ 947,00','€ 463,00','€ 700,00','Conceptfacturen','Naar verkoopfacturen','Brutomarge','Niet beschikbaar'] as $expected)overviewCheck(str_contains($html,$expected),'Missing overview evidence: '.$expected);
overviewCheck(!str_contains($html,'Geboekte verkoopfacturen uit BREBO nog niet aangesloten.'),'No stale sales placeholder');
putenv('OFFICE_BASE_PATH=/office');overviewCheck(!str_contains(OfficeFinanceView::render($data,'dashboard'),'Openstaande verkoopfacturen'),'Native overview never added to production');
echo "OFFICE_FINANCE_SALES_OVERVIEW_OK\n";
