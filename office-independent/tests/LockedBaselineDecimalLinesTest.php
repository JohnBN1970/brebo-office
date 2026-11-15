<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/WorkingBudget/LockedBaselineLines.php';
use Brebo\Office\WorkingBudget\LockedBaselineLines;
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
 echo "LOCKED_BASELINE_DECIMAL_LINES_TEST=SKIP_NO_SQLITE\n"; exit(0);
}
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_calc_crm_acceptance_transfers (id INTEGER, office_project_id INTEGER)');
$db->exec('CREATE TABLE office_calc_work_budget_baselines (id INTEGER, acceptance_transfer_id INTEGER, version_number INTEGER, locked_at TEXT)');
$db->exec('CREATE TABLE office_calc_work_budget_lines (id INTEGER, baseline_id INTEGER, source_line_id TEXT, cost_type TEXT, quantity INTEGER, unit_cost_cents INTEGER, budget_cents INTEGER, activity_ref TEXT, quantity_decimal TEXT, unit_cost_decimal TEXT, budget_decimal TEXT)');
$db->exec("INSERT INTO office_calc_crm_acceptance_transfers VALUES (1,42)");
$db->exec("INSERT INTO office_calc_work_budget_baselines VALUES (11,1,2,'2026-11-15')");
$db->exec("INSERT INTO office_calc_work_budget_lines VALUES (1,11,'labor-1','labor',1,1543,1543,'A','1.2500','12.3456','15.4320'),(2,11,'mat-1','material',2,100,200,'B',NULL,NULL,NULL)");
$result=(new LockedBaselineLines($db))->forProject(42);
if ($result===null || count($result['lines'])!==2) throw new RuntimeException('Missing baseline lines.');
if ($result['lines'][0]['quantity_decimal']!=='1.2500' || $result['lines'][0]['unit_cost_decimal']!=='12.3456' || $result['lines'][0]['budget_decimal']!=='15.4320') throw new RuntimeException('Decimal line lost.');
if ($result['lines'][1]['quantity_decimal']!==null) throw new RuntimeException('Legacy line mutated.');
$db->exec("UPDATE office_calc_work_budget_lines SET quantity_decimal='bad' WHERE id=1");
try { (new LockedBaselineLines($db))->forProject(42); throw new RuntimeException('Bad decimal accepted.'); }
catch(RuntimeException $e) { if($e->getMessage()==='Bad decimal accepted.') throw $e; }
echo "LOCKED_BASELINE_DECIMAL_LINES_TEST=PASS\n";
