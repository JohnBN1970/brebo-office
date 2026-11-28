<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Finance/OfficeFinanceAccess.php';
require_once __DIR__.'/../src/Finance/OfficeFinanceSalesOverview.php';
use Brebo\Office\Finance\OfficeFinanceSalesOverview;
putenv('OFFICE_FINANCE_CENTER_ENABLED=disabled');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach ([
"CREATE TABLE office_crm_relations (id INTEGER,relation_kind TEXT,status TEXT)",
"CREATE TABLE office_operator_accounts (id INTEGER,active INTEGER)",
"CREATE TABLE office_module_permissions (operator_id INTEGER,module TEXT,permission TEXT)",
"CREATE TABLE office_project_memberships (project_id INTEGER,operator_id INTEGER,permission TEXT)",
"CREATE TABLE office_finance_sales_invoice (id INTEGER,relation_id INTEGER,office_project_id INTEGER,operator_id INTEGER,invoice_date TEXT,due_date TEXT,net_cents INTEGER,gross_cents INTEGER,paid_cents INTEGER,status TEXT,invoice_number TEXT)",
"CREATE TABLE office_finance_invoices (id INTEGER,invoice_date TEXT,currency TEXT,subtotal TEXT,status TEXT)",
"CREATE TABLE office_finance_invoice_lines (invoice_id INTEGER,office_project_id INTEGER,net_amount TEXT)",
"INSERT INTO office_crm_relations VALUES (1,'person','active'),(2,'organization','active')",
"INSERT INTO office_operator_accounts VALUES (1,1)",
"INSERT INTO office_module_permissions VALUES (1,'finance','read')",
"INSERT INTO office_project_memberships VALUES (10,1,'read'),(20,1,'read')",
"INSERT INTO office_finance_sales_invoice VALUES (1,1,10,1,'2026-01-01','2026-02-01',10000,12100,12100,'sent','A'),(2,2,20,1,'2026-01-01','2026-02-01',20000,24200,24200,'sent','B')",
"INSERT INTO office_finance_invoices VALUES (1,'2026-01-02','EUR','40.00','booked'),(2,'2026-01-02','EUR','80.00','booked')",
"INSERT INTO office_finance_invoice_lines VALUES (1,10,'40.00'),(2,20,'80.00')"
] as $sql) $db->exec($sql);
$read=static fn()=>OfficeFinanceSalesOverview::read($db,'1',null,'2026-11-28');
$before=$read();
if($before['ytd_net_cents']!==30000 || $before['paid_gross_cents']!==36300 || $before['costs']['booked_net_cents']!==12000) throw new RuntimeException('Incorrect historical baseline');
foreach (['archived','active'] as $status) {
    $q=$db->prepare('UPDATE office_crm_relations SET status=? WHERE id=1');$q->execute([$status]);
    if($read()!==$before) throw new RuntimeException('Historical finance changed after '.$status);
}
echo "Historical finance archival regression passed\n";
