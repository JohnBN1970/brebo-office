<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/WorkingBudget/ProcurementCommitmentReconciliation.php';
require_once dirname(__DIR__) . '/src/WorkingBudget/ProcurementCostBridge.php';

use Brebo\Office\WorkingBudget\ProcurementCostBridge;

$procurement=['source'=>'office_procurement_orders','project_id'=>42,'orders'=>[
    ['order_id'=>7,'awarded_cents'=>10000],['order_id'=>8,'awarded_cents'=>5000]
]];
$finance=['source'=>'office_financecenter','project_id'=>42,
    'matched_by_order_cents'=>['7'=>4000,'8'=>6000],
    'posted_actual_cents'=>10000,'unmatched_actual_cents'=>0];
$pending=ProcurementCostBridge::join($procurement,$finance,false);
if ($pending['forecast_ready'] !== false || $pending['posted_actual_cents'] !== null) throw new RuntimeException('Incomplete Finance data exposed.');
$ok=ProcurementCostBridge::join($procurement,$finance,true);
if (!$ok['forecast_ready'] || $ok['open_commitment_cents']!==6000 || $ok['posted_actual_cents']!==10000) throw new RuntimeException('Incorrect reconciliation.');
$unmatched=$finance;
$unmatched['unmatched_actual_cents']=100;
$unmatched['posted_actual_cents']=10100;
$result=ProcurementCostBridge::join($procurement,$unmatched,true);
if ($result['forecast_ready'] !== false || $result['unmatched_actual_cents']!==100) throw new RuntimeException('Unmatched cost not flagged.');
foreach ([
    [$procurement,array_replace($finance,['project_id'=>99])],
    [$procurement,array_replace($finance,['matched_by_order_cents'=>['99'=>10000]])],
    [$procurement,array_replace($finance,['posted_actual_cents'=>9000])],
] as [$p,$f]) {
    $failed=false;
    try {ProcurementCostBridge::join($p,$f,true);}catch(InvalidArgumentException|RuntimeException $e){$failed=true;}
    if (!$failed) throw new RuntimeException('Invalid source combination accepted.');
}
echo "PROCUREMENT_COST_BRIDGE_TEST=PASS\n";
