<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcWorkBudgetHandoff.php';

use Brebo\Office\Calculation\OfficeCalcWorkBudgetHandoff;

$acceptance = [
    'crm_opportunity_id' => 'crm-2026-11',
    'calculation_id' => 42,
    'accepted_revision' => 'v3',
    'status' => 'accepted',
];
$costs = [
    'labor' => 4500,
    'material' => 12500,
    'equipment' => 2000,
    'subcontract' => 30000,
    'other' => 750,
];
$lines = [];
foreach ($costs as $type => $unitCents) {
    $lines[] = [
        'line_id' => 'L1-' . $type,
        'cost_type' => $type,
        'quantity' => 2,
        'unit_cost_cents' => $unitCents,
        'activity_ref' => 'A1',
    ];
}
$budget = OfficeCalcWorkBudgetHandoff::prepare($acceptance, $lines);
if (count($budget['items']) !== 5 || $budget['baseline_locked'] !== true) {
    throw new RuntimeException('All five cost carriers must survive handoff');
}
$total = 0;
foreach ($budget['items'] as $index => $item) {
    $total += $item['budget_cents'];
    if ($item['source_line_id'] !== $lines[$index]['line_id']
        || $item['cost_type'] !== $lines[$index]['cost_type']
        || $item['activity_ref'] !== 'A1') {
        throw new RuntimeException('Source reference or cost carrier lost');
    }
}
if ($total !== 99500) {
    throw new RuntimeException('Incorrect budget total in cents');
}
foreach ([
    ['line_id' => 'bad', 'cost_type' => 'profit', 'quantity' => 1, 'unit_cost_cents' => 100],
    ['line_id' => 'bad', 'cost_type' => 'labor', 'quantity' => -1, 'unit_cost_cents' => 100],
    ['line_id' => 'bad', 'cost_type' => 'labor', 'quantity' => 1, 'unit_cost_cents' => -100],
] as $invalid) {
    try {
        OfficeCalcWorkBudgetHandoff::prepare($acceptance, [$invalid]);
    } catch (InvalidArgumentException $expected) {
        continue;
    }
    throw new RuntimeException('Invalid budget line accepted');
}
echo "CALC_FIVE_COST_CARRIERS_HANDOFF=PASS\n";
