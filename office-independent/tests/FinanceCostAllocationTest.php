<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/WorkingBudget/FinanceCostAllocation.php';
use Brebo\Office\WorkingBudget\FinanceCostAllocation as Allocation;

$base=['source'=>'office_financecenter','project_id'=>42,'status'=>'posted','currency'=>'EUR','vat_basis'=>'excluding'];
$rows=[
    $base+['invoice_line_id'=>1,'order_id'=>7,'amount_cents'=>1000],
    $base+['invoice_line_id'=>2,'order_id'=>7,'amount_cents'=>2000],
    $base+['invoice_line_id'=>3,'order_id'=>null,'amount_cents'=>500],
];
$r=Allocation::summarize(42,$rows);
if ($r['posted_actual_cents']!==3500 || $r['matched_by_order_cents']['7']!==3000
    || $r['unmatched_actual_cents']!==500 || $r['unmatched_line_count']!==1) {
    throw new RuntimeException('Incorrect posted-cost allocation.');
}
foreach ([
    [$rows[0],$rows[0]],
    [array_replace($rows[0],['project_id'=>43])],
    [array_replace($rows[0],['source'=>'moneybird'])],
    [array_replace($rows[0],['status'=>'draft'])],
    [array_replace($rows[0],['amount_cents'=>-1])],
] as $bad) {
    $rejected=false;
    try { Allocation::summarize(42,$bad); } catch (InvalidArgumentException $e) { $rejected=true; }
    if (!$rejected) throw new RuntimeException('Invalid source was accepted.');
}
echo "FINANCE_COST_ALLOCATION_TEST=PASS\n";
