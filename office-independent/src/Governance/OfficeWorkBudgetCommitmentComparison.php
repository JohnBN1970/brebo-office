<?php
declare(strict_types=1);
namespace Brebo\Office\Governance;

/**
 * Read-only project-level comparison. Budget is an accepted, locked baseline;
 * procurement commitments are awarded orders, never booked actual costs.
 * Missing baseline is not zero budget.
 */
final class OfficeWorkBudgetCommitmentComparison
{
    public static function summarize(?array $baseline, array $commitment): array
    {
        $project = filter_var($commitment['project_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!is_int($project) || ($commitment['currency'] ?? null) !== 'EUR'
            || ($commitment['vat_basis'] ?? null) !== 'excluding'
            || ($commitment['source'] ?? null) !== 'office_procurement_orders') {
            throw new \InvalidArgumentException('Ongeldige inkoopbron.');
        }
        $awarded = self::amount($commitment['commitment_cents'] ?? null);
        if ($baseline === null) {
            return ['project_id'=>$project,'state'=>'baseline_missing',
                'budget_cents'=>null,'commitment_cents'=>$awarded,'remaining_cents'=>null,
                'variance_cents'=>null,'message'=>'Geen vastgestelde werkbegroting beschikbaar'];
        }
        if (($baseline['project_id'] ?? null) !== $project || ($baseline['locked'] ?? null) !== true
            || ($baseline['currency'] ?? null) !== 'EUR'
            || ($baseline['vat_basis'] ?? null) !== 'excluding') {
            throw new \InvalidArgumentException('Werkbegroting is niet vastgesteld of hoort bij een ander project.');
        }
        $budget = self::amount($baseline['procurement_budget_cents'] ?? null);
        return ['project_id'=>$project,'state'=>'comparable',
            'budget_cents'=>$budget,'commitment_cents'=>$awarded,
            'remaining_cents'=>$budget-$awarded,'variance_cents'=>$awarded-$budget,
            'message'=>'Vergelijking betreft inkoopverplichtingen, niet werkelijk geboekte kosten'];
    }

    private static function amount(mixed $value): int
    {
        if (!is_int($value) || $value<0) throw new \InvalidArgumentException('Bedrag moet een niet-negatief aantal eurocenten zijn.');
        return $value;
    }
}
