<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/WorkingBudget/LockedBaselineLines.php';
require_once dirname(__DIR__) . '/src/WorkingBudget/WorkingBudgetKpiProjection.php';
require_once dirname(__DIR__) . '/src/WorkingBudget/ProjectBudgetReadModel.php';
use Brebo\Office\WorkingBudget\ProjectBudgetReadModel;

$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_calc_crm_acceptance_transfers(id INTEGER PRIMARY KEY, office_project_id INTEGER)');
$db->exec('CREATE TABLE office_calc_work_budget_baselines(id INTEGER PRIMARY KEY, acceptance_transfer_id INTEGER, version_number INTEGER, locked_at TEXT)');
$db->exec('CREATE TABLE office_calc_work_budget_lines(id INTEGER PRIMARY KEY, baseline_id INTEGER, source_line_id INTEGER, cost_type TEXT, quantity INTEGER, unit_cost_cents INTEGER, budget_cents INTEGER, activity_ref TEXT)');
$reader=new ProjectBudgetReadModel($db);
$empty=$reader->forAuthorizedProject(42);
if ($empty['kpis']['state']!=='baseline_missing' || $empty['cost_lines']!==[]) throw new RuntimeException('Missing baseline incorrectly presented.');
$db->exec("INSERT INTO office_calc_crm_acceptance_transfers VALUES (1,42)");
$db->exec("INSERT INTO office_calc_work_budget_baselines VALUES (3,1,1,'2026-11-11 10:00:00')");
$db->exec("INSERT INTO office_calc_work_budget_lines VALUES (1,3,9,'labor',3,3000,9000,'A')");
$with=$reader->forAuthorizedProject(42);
if ($with['kpis']['baseline_cents']!==9000 || $with['baseline_id']!==3
    || $with['source_status']['labor']!=='not_connected' || $with['kpis']['forecast_cents']!==null) {
    throw new RuntimeException('Invalid project budget read model.');
}
echo "PROJECT_BUDGET_READ_MODEL_TEST=PASS\n";
