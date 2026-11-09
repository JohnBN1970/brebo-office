<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmWorkBudgetHandoffStatus.php';
use Brebo\Office\Crm\OfficeCrmWorkBudgetHandoffStatus;
if (!extension_loaded('pdo_sqlite')) { echo "SKIP pdo_sqlite\n"; exit(0); }
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_crm_opportunities (id INTEGER PRIMARY KEY,stage TEXT,status TEXT)');
$db->exec('CREATE TABLE office_calc_crm_acceptance_transfers (id INTEGER PRIMARY KEY,crm_opportunity_id TEXT,office_project_id INTEGER)');
$db->exec('CREATE TABLE office_calc_work_budget_baselines (id INTEGER PRIMARY KEY,acceptance_transfer_id INTEGER,locked_at TEXT,version_number INTEGER)');
$db->exec("INSERT INTO office_crm_opportunities VALUES (1,'proposal','open'),(2,'won','closed'),(3,'won','closed'),(4,'won','closed')");
$db->exec("INSERT INTO office_calc_crm_acceptance_transfers VALUES (10,'3',200),(11,'4',201)");
$db->exec("INSERT INTO office_calc_work_budget_baselines VALUES (20,11,'2026-11-08 12:00:00',1)");
$s=new OfficeCrmWorkBudgetHandoffStatus($db);
foreach ([1=>'not_yet_accepted',2=>'won_not_transferred',3=>'project_without_locked_budget',4=>'transferred_locked',5=>'not_found'] as $id=>$expected) {
    $actual=$s->forOpportunity($id);
    if ($actual['state']!==$expected) throw new RuntimeException("Unexpected state $id");
}
if ($s->forOpportunity(4)['project_id']!==201 || $s->forOpportunity(4)['baseline_id']!==20) throw new RuntimeException('Wrong handoff');
echo "CRM_WORK_BUDGET_HANDOFF_STATUS_PASS\n";
