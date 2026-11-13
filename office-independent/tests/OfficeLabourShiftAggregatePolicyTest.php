<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeLabourShiftAggregatePolicy.php';
use Brebo\Office\Inzet\OfficeLabourShiftAggregatePolicy as Policy;
$case=Policy::calculate(8*3600,[
    ['approval_id'=>101,'approved_seconds'=>2*3600],
    ['approval_id'=>102,'approved_seconds'=>4*3600],
]);
if ($case !== [
    'approved_seconds'=>21600,
    'budget_debit_seconds'=>28800,
    'variance_seconds'=>-7200,
    'approval_count'=>2,
]) throw new RuntimeException('Two intervals must yield 6 approved, 8 budget and -2 variance');
$case=Policy::calculate(8*3600,[['approval_id'=>103,'approved_seconds'=>10*3600]]);
if ($case['variance_seconds']!==7200 || $case['budget_debit_seconds']!==28800) {
    throw new RuntimeException('Overtime must not double-debit a shift');
}
foreach ([
    [28800,[]],
    [0,[['approval_id'=>1,'approved_seconds'=>3600]]],
    [28800,[['approval_id'=>1,'approved_seconds'=>3600],['approval_id'=>1,'approved_seconds'=>3600]]],
    [28800,[['approval_id'=>1,'approved_seconds'=>0]]],
    [28800,[['approval_id'=>1,'approved_seconds'=>50000],['approval_id'=>2,'approved_seconds'=>50000]]],
] as [$planned,$approvals]) {
    $rejected=false;
    try { Policy::calculate($planned,$approvals); } catch (InvalidArgumentException|DomainException $expected) { $rejected=true; }
    if (!$rejected) throw new RuntimeException('Invalid aggregate must fail closed');
}
echo "LABOUR_SHIFT_AGGREGATE_POLICY_OK\n";
