<?php
declare(strict_types=1);

namespace Brebo\Office\WorkingBudget;

use InvalidArgumentException;

/**
 * Pure, side-effect-free budget forecast calculator.
 * All amounts are integer eurocents excluding VAT.
 * Input rows: code, baseline_cents, approved_changes_cents,
 * actual_cents, open_commitments_cents, remaining_uncommitted_cents.
 * Open commitments MUST exclude portions already recognized as actuals.
 */
final class BudgetForecast
{
    public static function calculate(array $rows): array
    {
        $totals = [
            'baseline_cents' => 0,
            'approved_changes_cents' => 0,
            'current_budget_cents' => 0,
            'actual_cents' => 0,
            'open_commitments_cents' => 0,
            'remaining_uncommitted_cents' => 0,
            'forecast_cents' => 0,
            'variance_cents' => 0,
        ];
        $details = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['code']) || !is_string($row['code']) || trim($row['code']) === '') {
                throw new InvalidArgumentException('Each budget line requires a nonempty monitoring code.');
            }
            $code = trim($row['code']);
            if (isset($seen[$code])) {
                throw new InvalidArgumentException('Duplicate monitoring code: ' . $code);
            }
            $seen[$code] = true;
            $values = [];
            foreach (['baseline_cents', 'approved_changes_cents', 'actual_cents', 'open_commitments_cents', 'remaining_uncommitted_cents'] as $key) {
                if (!array_key_exists($key, $row) || !is_int($row[$key])) {
                    throw new InvalidArgumentException($code . ': ' . $key . ' must be integer cents.');
                }
                if ($key !== 'approved_changes_cents' && $row[$key] < 0) {
                    throw new InvalidArgumentException($code . ': negative value not allowed for ' . $key);
                }
                $values[$key] = $row[$key];
            }
            $values['current_budget_cents'] = $values['baseline_cents'] + $values['approved_changes_cents'];
            if ($values['current_budget_cents'] < 0) {
                throw new InvalidArgumentException($code . ': approved budget cannot be negative.');
            }
            $values['forecast_cents'] = $values['actual_cents'] + $values['open_commitments_cents'] + $values['remaining_uncommitted_cents'];
            $values['variance_cents'] = $values['current_budget_cents'] - $values['forecast_cents'];
            $details[] = ['code' => $code] + $values;
            foreach ($values as $key => $value) {
                $totals[$key] += $value;
            }
        }
        return ['totals' => $totals, 'lines' => $details];
    }
}
