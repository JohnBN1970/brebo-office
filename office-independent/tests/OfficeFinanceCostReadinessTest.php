<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Finance/OfficeFinanceAccess.php';
require dirname(__DIR__).'/src/Finance/OfficeFinanceCostReadiness.php';
use Brebo\Office\Finance\OfficeFinanceCostReadiness as Costs;
function costCheck(bool $ok,string $why): void{if(!$ok)throw new RuntimeException($why);}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE office_operator_accounts(id INTEGER,active INTEGER);INSERT INTO office_operator_accounts VALUES(1,1),(2,1);
CREATE TABLE office_module_permissions(operator_id INTEGER,module TEXT,permission TEXT);INSERT INTO office_module_permissions VALUES(1,'finance','read');
CREATE TABLE office_project_memberships(operator_id INTEGER,project_id INTEGER,permission TEXT);INSERT INTO office_project_memberships VALUES(1,10,'read');
CREATE TABLE office_finance_invoices(id INTEGER,invoice_date TEXT,currency TEXT,subtotal TEXT,status TEXT);
INSERT INTO office_finance_invoices VALUES(1,'2026-11-01','EUR','125.00','draft'),(2,'2026-11-01','EUR','50.00','booked'),(3,'2026-11-01','EUR','120.00','draft'),(4,'2026-11-01','EUR','120.00','draft'),(5,'2026-11-01','EUR','20.00','draft'),(6,'2026-11-01','EUR','10.00','cancelled'),(7,'2026-11-01','EUR','10.00','approved');
CREATE TABLE office_finance_invoice_lines(invoice_id INTEGER,office_project_id INTEGER,net_amount TEXT);
INSERT INTO office_finance_invoice_lines VALUES(1,10,'100.00'),(1,10,'25.00'),(2,10,'50.00'),(3,10,'100.00'),(3,20,'20.00'),(4,10,'100.00'),(5,NULL,'20.00'),(6,10,'10.00'),(7,10,'10.00');");
$before=$db->query('SELECT total_changes()')->fetchColumn();$r=Costs::read($db,'1',null,'2026-11-07');
costCheck($r['available']&&$r['draft_net_cents']===12500&&$r['booked_net_cents']===5000,'Only complete authorized native amounts');
costCheck($r['draft_count']===1&&$r['booked_count']===1&&$r['unusable_count']===2,'Approved is not silently treated as booked; mismatched amounts stay out');
costCheck(!$r['labour_valued']&&!$r['overhead_booked'],'No inferred labour rates or overhead');
costCheck($db->query('SELECT total_changes()')->fetchColumn()===$before,'No source writes');
foreach([['2',null],['1',20]] as [$op,$project]){try{Costs::read($db,$op,$project,'2026-11-07');throw new RuntimeException('Access accepted');}catch(DomainException){}}
$h=Costs::cashCards(['available'=>true,'receipts_by_days'=>[30=>10000,60=>15000,90=>20000]]);foreach(['30 dagen','€ 100,00','€ 150,00','€ 200,00','Cumulatief','beginsaldo'] as $s)costCheck(str_contains($h,$s),'Missing cash evidence');
costCheck(str_contains(Costs::cards($r),'Onvolledig'),'Partial costs never claim full operating profit');
echo "OFFICE_FINANCE_COST_READINESS_OK\n";
