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
    /**
     * Count only a single explicitly signed/accepted EUR contract per project.
     * Drafts, ambiguous revisions and incomplete coverage fail closed.
     */
    public static function acceptedContractValues(PDO $db, array $projects): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn(array $p): int => (int)($p['id'] ?? 0), $projects
        ), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return ['cents' => null, 'covered' => 0, 'total' => 0];
        require_once __DIR__ . '/OfficeProjectContractRepository.php';
        require_once __DIR__ . '/OfficeProjectChangeOrderRepository.php';
        require_once __DIR__ . '/OfficeProjectContractValue.php';
        $contracts = new OfficeProjectContractRepository($db);
        $changes = new OfficeProjectChangeOrderRepository($db);
        $sum = 0;
        $covered = 0;
        foreach ($ids as $id) {
            $eligible = array_values(array_filter(
                $contracts->forProject($id),
                static fn(array $contract): bool =>
                    in_array(strtolower((string)($contract['status'] ?? '')), ['signed', 'accepted'], true)
                    && !empty($contract['signed_at'])
                    && ($contract['currency'] ?? '') === 'EUR'
            ));
            if (count($eligible) !== 1) continue;
            $contract = $eligible[0];
            $value = OfficeProjectContractValue::calculate(
                (string)$contract['contract_amount'],
                $changes->approvedForContract((int)$contract['id'])
            );
            $amount = (string)$value['current'];
            if (!preg_match('/^(0|[1-9][0-9]{0,13})\\.[0-9]{2}$/D', $amount)) {
                throw new \RuntimeException('Ongeldige geaccepteerde contractwaarde.');
            }
            [$euros, $fraction] = explode('.', $amount);
            $cents = (int)$euros * 100 + (int)$fraction;
            if ($cents < 0 || $cents > PHP_INT_MAX - $sum) {
                throw new \RuntimeException('Contracttotaal buiten bereik.');
            }
            $sum += $cents;
            ++$covered;
        }
        return ['cents' => $covered === count($ids) ? $sum : null,
                'covered' => $covered, 'total' => count($ids)];
    }

}
