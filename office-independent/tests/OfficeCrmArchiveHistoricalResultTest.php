<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Finance/OfficeFinanceAccess.php';
require_once __DIR__.'/../src/Finance/Center/Report.php';
use Brebo\Office\Finance\Center\Report;
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->sqliteCreateFunction('CONCAT',static fn(...$parts)=>implode('',$parts));
foreach ([
"CREATE TABLE office_crm_relations(id INTEGER,status TEXT)",
"CREATE TABLE office_operator_accounts(id INTEGER,active INTEGER)",
"CREATE TABLE office_module_permissions(operator_id INTEGER,module TEXT,permission TEXT)",
"CREATE TABLE office_project_memberships(project_id INTEGER,operator_id INTEGER,permission TEXT)",
"CREATE TABLE office_finance_sales_invoice(id INTEGER,relation_id INTEGER,operator_id INTEGER,office_project_id INTEGER,invoice_number TEXT,invoice_date TEXT,due_date TEXT,net_cents INTEGER,gross_cents INTEGER,paid_cents INTEGER,status TEXT,revision INTEGER,customer_json TEXT)",
"CREATE TABLE office_finance_sales_context(invoice_id INTEGER,g_cents INTEGER)",
"CREATE TABLE office_finance_postings(id INTEGER,operator_id INTEGER,booking_date TEXT,revision INTEGER,status TEXT,source_kind TEXT)",
"CREATE TABLE office_finance_posting_lines(id INTEGER,posting_id INTEGER,office_project_id INTEGER,category TEXT,net_cents INTEGER)",
"CREATE TABLE office_finance_invoices(id INTEGER,supplier_name TEXT,invoice_number TEXT,invoice_date TEXT,total TEXT,status TEXT,created_by INTEGER)",
"CREATE TABLE office_finance_invoice_lines(invoice_id INTEGER,office_project_id INTEGER)",
"CREATE TABLE office_inzet_approved_hours(id INTEGER,person_id INTEGER,project_id INTEGER,approved_seconds INTEGER,approved_at TEXT,work_date TEXT)",
"CREATE TABLE office_inzet_actual_labour_costs(id INTEGER,approved_hours_id INTEGER,project_id INTEGER,work_date TEXT,amount_cents INTEGER,source_sha256 TEXT)",
"CREATE TABLE office_finance_cash_plan(id INTEGER,operator_id INTEGER,due_date TEXT)",
"CREATE TABLE office_finance_period_reviews(id INTEGER,operator_id INTEGER,through_date TEXT,source_hash TEXT,costs_complete INTEGER,hours_complete INTEGER,overhead_complete INTEGER,cash_complete INTEGER)",
"INSERT INTO office_crm_relations VALUES(1,'active')",
"INSERT INTO office_operator_accounts VALUES(1,1)",
"INSERT INTO office_module_permissions VALUES(1,'finance','read')",
"INSERT INTO office_project_memberships VALUES(10,1,'read')",
"INSERT INTO office_finance_sales_invoice VALUES(1,1,1,10,'A','2026-01-01','2026-02-01',10000,12100,12100,'sent',1,'{}')",
"INSERT INTO office_finance_postings VALUES(1,1,'2026-01-02',1,'booked','purchase')",
"INSERT INTO office_finance_posting_lines VALUES(1,1,10,'direct',2000),(2,1,10,'overhead',500)",
"INSERT INTO office_inzet_approved_hours VALUES(1,1,10,3600,'2026-01-02','2026-01-02')",
"INSERT INTO office_inzet_actual_labour_costs VALUES(1,1,10,'2026-01-02',3000,'fixture')"
] as $sql) $db->exec($sql);
$report=new Report($db,'1');
$initial=$report->read('2026-11-28');
$q=$db->prepare("INSERT INTO office_finance_period_reviews VALUES(1,1,'2026-11-28',?,1,1,1,0)");
$q->execute([$initial['source_hash']]);
$before=$report->read('2026-11-28');
if(!$before['cost_review_ready'] || $before['labor_cents']!==3000 || $before['gross_margin_cents']!==5000 || $before['operating_result_cents']!==4500) throw new RuntimeException('Invalid baseline');
foreach(['archived','active'] as $status){
    $db->prepare('UPDATE office_crm_relations SET status=? WHERE id=1')->execute([$status]);
    if($report->read('2026-11-28')!==$before) throw new RuntimeException('Historical result changed: '.$status);
}
echo "Historical FinanceCenter result regression passed\n";
