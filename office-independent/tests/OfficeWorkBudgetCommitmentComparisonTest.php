<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Governance/OfficeWorkBudgetCommitmentComparison.php';
require_once __DIR__.'/../src/Governance/OfficeWorkBudgetBaselineReader.php';

use Brebo\Office\Governance\OfficeWorkBudgetCommitmentComparison as Comparison;
use Brebo\Office\Governance\OfficeWorkBudgetBaselineReader as Reader;

$commitment=['source'=>'office_procurement_orders','project_id'=>12,'currency'=>'EUR','vat_basis'=>'excluding','commitment_cents'=>8500];
$missing=Comparison::summarize(null,$commitment);
assert($missing['state']==='baseline_missing' && $missing['budget_cents']===null && $missing['remaining_cents']===null);
$baseline=['project_id'=>12,'locked'=>true,'currency'=>'EUR','vat_basis'=>'excluding','procurement_budget_cents'=>10000];
$ok=Comparison::summarize($baseline,$commitment);
assert($ok['remaining_cents']===1500 && $ok['variance_cents']===-1500);
$commitment['commitment_cents']=12000;
$over=Comparison::summarize($baseline,$commitment);
assert($over['remaining_cents']===-2000 && $over['variance_cents']===2000);
foreach([
    [array_replace($baseline,['project_id'=>13]),$commitment],
    [array_replace($baseline,['locked'=>false]),$commitment],
    [$baseline,array_replace($commitment,['source'=>'invoices'])],
] as [$b,$c]) {
    $failed=false;
    try {Comparison::summarize($b,$c);}catch(InvalidArgumentException){$failed=true;}
    if(!$failed)throw new RuntimeException('Expected rejection');
}
if(in_array('sqlite',PDO::getAvailableDrivers(),true)) {
    $db=new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE office_calc_crm_acceptance_transfers (id INTEGER,office_project_id INTEGER)');
    $db->exec('CREATE TABLE office_calc_work_budget_baselines (id INTEGER,acceptance_transfer_id INTEGER,version_number INTEGER,locked_at TEXT)');
    $db->exec('CREATE TABLE office_calc_work_budget_lines (baseline_id INTEGER,cost_type TEXT,budget_cents INTEGER)');
    $db->exec("INSERT INTO office_calc_crm_acceptance_transfers VALUES (1,12)");
    $db->exec("INSERT INTO office_calc_work_budget_baselines VALUES (2,1,1,'2026-11-08')");
    $db->exec("INSERT INTO office_calc_work_budget_lines VALUES (2,'labor',5000),(2,'material',10000),(2,'subcontract',20000)");
    $read=(new Reader($db))->forProject(12);
    if($read===null || $read['procurement_budget_cents']!==30000 || $read['procurement_line_count']!==2)throw new RuntimeException('Wrong baseline');
    if((new Reader($db))->forProject(13)!==null)throw new RuntimeException('Invented baseline');
}
echo "WORK_BUDGET_COMMITMENT_CONTRACT_PASS\n";
