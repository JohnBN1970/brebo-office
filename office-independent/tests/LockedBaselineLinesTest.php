<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/WorkingBudget/LockedBaselineLines.php';

use Brebo\Office\WorkingBudget\LockedBaselineLines;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "LOCKED_BASELINE_LINES_TEST=SKIP_NO_SQLITE\n";
    exit(0);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_calc_crm_acceptance_transfers (id INTEGER, office_project_id INTEGER)');
$db->exec('CREATE TABLE office_calc_work_budget_baselines (id INTEGER, acceptance_transfer_id INTEGER, version_number INTEGER, locked_at TEXT)');
$db->exec('CREATE TABLE office_calc_work_budget_lines (id INTEGER, baseline_id INTEGER, source_line_id TEXT, cost_type TEXT, budget_cents INTEGER, activity_ref TEXT)');
$db->exec("INSERT INTO office_calc_crm_acceptance_transfers VALUES (1,42)");
$db->exec("INSERT INTO office_calc_work_budget_baselines VALUES (10,1,1,'2026-11-11'),(11,1,2,'2026-11-12')");
$db->exec("INSERT INTO office_calc_work_budget_lines VALUES (1,10,'old','labor',100,'A'),(2,11,'labor-1','labor',5000,'A'),(3,11,'mat-1','material',3000,'B')");
$reader = new LockedBaselineLines($db);
$result = $reader->forProject(42);
if ($result === null || $result['baseline_id'] !== 11 || count($result['lines']) !== 2 || $result['lines'][0]['baseline_cents'] !== 5000) {
    throw new RuntimeException('Wrong locked baseline version or lines.');
}
if ($reader->forProject(43) !== null) {
    throw new RuntimeException('Missing baseline must not become zero budget.');
}
$db->exec("UPDATE office_calc_work_budget_baselines SET locked_at=NULL WHERE id=11");
try {
    $reader->forProject(42);
    throw new RuntimeException('Unlocked baseline accepted.');
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'Unlocked baseline accepted.') throw $e;
}
echo "LOCKED_BASELINE_LINES_TEST=PASS\n";
