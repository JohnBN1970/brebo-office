<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

use PDO;

/** Read-only, ACL-scoped portfolio totals; missing sources are never zero. */
final class OfficeProjectPortfolioTotals
{
    public static function lockedProcurementBudgets(PDO $db, array $projects): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn(array $p): int => (int)($p['id'] ?? 0), $projects
        ), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return ['cents' => null, 'covered' => 0, 'total' => 0];
        require_once dirname(__DIR__) . '/Governance/OfficeWorkBudgetBaselineReader.php';
        $reader = new \Brebo\Office\Governance\OfficeWorkBudgetBaselineReader($db);
        $sum = 0;
        $covered = 0;
        foreach ($ids as $id) {
            $baseline = $reader->forProject($id);
            if ($baseline === null) continue;
            $cents = $baseline['procurement_budget_cents'] ?? null;
            if (!is_int($cents) || $cents < 0 || $cents > PHP_INT_MAX - $sum) {
                throw new \RuntimeException('Ongeldige werkbegrotingswaarde.');
            }
            $sum += $cents;
            ++$covered;
        }
        // Partial coverage is not a reliable portfolio-wide total.
        return ['cents' => $covered === count($ids) ? $sum : null,
                'covered' => $covered, 'total' => count($ids)];
    }
}
