<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/WorkingBudget/BudgetForecast.php';

use Brebo\Office\WorkingBudget\BudgetForecast;

function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$forecast = BudgetForecast::calculate([
    ['code' => 'ARBEID', 'baseline_cents' => 8000000, 'approved_changes_cents' => 0, 'actual_cents' => 4200000, 'open_commitments_cents' => 0, 'remaining_uncommitted_cents' => 4300000],
    ['code' => 'INKOOP', 'baseline_cents' => 11000000, 'approved_changes_cents' => 500000, 'actual_cents' => 6200000, 'open_commitments_cents' => 3200000, 'remaining_uncommitted_cents' => 1800000],
]);
check($forecast['totals']['baseline_cents'] === 19000000, 'baseline');
check($forecast['totals']['current_budget_cents'] === 19500000, 'approved change');
check($forecast['totals']['forecast_cents'] === 19700000, 'forecast');
check($forecast['totals']['variance_cents'] === -200000, 'variance');
check($forecast['lines'][0]['variance_cents'] === -500000, 'labor variance');
check(BudgetForecast::calculate([])['totals']['forecast_cents'] === 0, 'empty budget');

foreach ([
    [['code' => 'X', 'baseline_cents' => 1, 'approved_changes_cents' => 0, 'actual_cents' => 0, 'open_commitments_cents' => 0, 'remaining_uncommitted_cents' => 0],
     ['code' => 'X', 'baseline_cents' => 1, 'approved_changes_cents' => 0, 'actual_cents' => 0, 'open_commitments_cents' => 0, 'remaining_uncommitted_cents' => 0]],
    [['code' => 'X', 'baseline_cents' => 1.2, 'approved_changes_cents' => 0, 'actual_cents' => 0, 'open_commitments_cents' => 0, 'remaining_uncommitted_cents' => 0]],
    [['code' => 'X', 'baseline_cents' => 1, 'approved_changes_cents' => -2, 'actual_cents' => 0, 'open_commitments_cents' => 0, 'remaining_uncommitted_cents' => 0]],
] as $invalid) {
    $rejected = false;
    try { BudgetForecast::calculate($invalid); } catch (InvalidArgumentException $e) { $rejected = true; }
    check($rejected, 'invalid input must be rejected');
}
echo "WORKING_BUDGET_FORECAST_TEST=PASS\n";
