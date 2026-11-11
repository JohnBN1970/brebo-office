<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcCostCarrierHandoffMapper.php';
require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcWorkBudgetHandoff.php';

use Brebo\Office\Calculation\OfficeCalcCostCarrierHandoffMapper as Mapper;
use Brebo\Office\Calculation\OfficeCalcWorkBudgetHandoff as Handoff;

$rows = [[
    'line_id' => 'kozijnen-1',
    'quantity' => 2,
    'activity_ref' => 'montage',
    'unit_costs_cents' => [
        'labor' => 4500,
        'material' => 12500,
        'equipment' => 2000,
        'subcontract' => 30000,
        'other' => 750,
    ],
    'selling_price_cents' => 75000,
    'profit_cents' => 10000,
]];
$mapped = Mapper::map($rows);
$accepted = ['crm_opportunity_id' => 'crm-1', 'calculation_id' => 1, 'accepted_revision' => 'v1', 'status' => 'accepted'];
$budget = Handoff::prepare($accepted, $mapped);
if (count($budget['items']) !== 5 || array_sum(array_column($budget['items'], 'budget_cents')) !== 99500) {
    throw new RuntimeException('Five cost carriers must preserve direct costs only');
}
foreach ($budget['items'] as $item) {
    if (!str_starts_with($item['source_line_id'], 'kozijnen-1:') || $item['activity_ref'] !== 'montage') {
        throw new RuntimeException('Lost source identity');
    }
}
foreach ([
    [['line_id'=>'x', 'quantity'=>1, 'unit_costs_cents'=>['profit'=>10]]],
    [['line_id'=>'x', 'quantity'=>-1, 'unit_costs_cents'=>['labor'=>10]]],
    [['line_id'=>'x', 'quantity'=>1, 'unit_costs_cents'=>['labor'=>-10]]],
    [['line_id'=>'x', 'quantity'=>PHP_INT_MAX, 'unit_costs_cents'=>['labor'=>2]]],
] as $invalid) {
    try { Mapper::map($invalid); }
    catch (InvalidArgumentException $expected) { continue; }
    throw new RuntimeException('Invalid native cost row accepted');
}
echo "CALC_NATIVE_COST_CARRIER_MAPPING=PASS\n";
