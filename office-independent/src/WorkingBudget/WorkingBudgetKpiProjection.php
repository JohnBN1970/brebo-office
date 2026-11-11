<?php
declare(strict_types=1);

namespace Brebo\Office\WorkingBudget;

use InvalidArgumentException;

/**
 * Read-only project KPI projection. Null is deliberate for unverified sources.
 * Do not label an incomplete cost forecast as a project margin.
 */
final class WorkingBudgetKpiProjection
{
    public static function fromSources(?array $baseline, ?array $procurement, ?array $finance): array
    {
        if ($baseline === null) {
            return ['state'=>'baseline_missing','baseline_cents'=>null,
                'awarded_cents'=>null,'actual_cents'=>null,'open_commitment_cents'=>null,
                'forecast_cents'=>null,'variance_cents'=>null];
        }
        if (($baseline['currency'] ?? null) !== 'EUR'
            || ($baseline['vat_basis'] ?? null) !== 'excluding'
            || !isset($baseline['project_id'], $baseline['lines'])
            || !is_array($baseline['lines'])) {
            throw new InvalidArgumentException('Invalid locked baseline.');
        }
        $budget = 0;
        foreach ($baseline['lines'] as $line) {
            $amount = $line['baseline_cents'] ?? null;
            if (!is_int($amount) || $amount < 0 || $amount > PHP_INT_MAX - $budget) {
                throw new InvalidArgumentException('Invalid baseline amount.');
            }
            $budget += $amount;
        }
        $result = ['state'=>'sources_incomplete','project_id'=>$baseline['project_id'],
            'baseline_cents'=>$budget,'awarded_cents'=>null,'actual_cents'=>null,
            'open_commitment_cents'=>null,'forecast_cents'=>null,'variance_cents'=>null];
        if ($procurement === null || ($procurement['project_id'] ?? null) !== $baseline['project_id']) return $result;
        $result['awarded_cents'] = $procurement['awarded_cents'] ?? null;
        if ($finance === null || ($finance['project_id'] ?? null) !== $baseline['project_id']) return $result;
        if (($finance['state'] ?? null) !== 'reconciled') return $result;
        $result['actual_cents'] = $finance['posted_actual_cents'];
        $result['open_commitment_cents'] = $finance['open_commitment_cents'];
        // Labor actuals, remaining work, approved changes and unmatched
        // non-procurement costs are not yet complete. No EAC or variance.
        return $result;
    }
}
