<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use InvalidArgumentException;

/** Revenue attribution is reporting-only: invoices stay on their actual legal debtor. */
final class OfficeCrmManagerRevenue {
    public static function totals(array $invoiceRows, array $managementLinks, string $asOf): array {
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D', $asOf)) throw new InvalidArgumentException('Invalid report date');
        $managerByVve = [];
        foreach ($managementLinks as $link) {
            if (($link['representation_type'] ?? null) !== 'property_management') continue;
            if (($link['valid_from'] ?? null) !== null && $link['valid_from'] > $asOf) continue;
            if (($link['valid_until'] ?? null) !== null && $link['valid_until'] < $asOf) continue;
            $vve = (int)($link['principal_relation_id'] ?? 0);
            $manager = (int)($link['representative_relation_id'] ?? 0);
            if ($vve < 1 || $manager < 1 || $vve === $manager) throw new InvalidArgumentException('Invalid management link');
            if (isset($managerByVve[$vve]) && $managerByVve[$vve] !== $manager) {
                throw new InvalidArgumentException('Ambiguous manager attribution');
            }
            $managerByVve[$vve] = $manager;
        }
        $totals = [];
        $seen = [];
        foreach ($invoiceRows as $row) {
            $invoice = (string)($row['invoice_id'] ?? '');
            $vve = (int)($row['customer_relation_id'] ?? 0);
            $amount = $row['net_revenue_cents'] ?? null;
            if ($invoice === '' || $vve < 1 || !is_int($amount) || isset($seen[$invoice])) {
                throw new InvalidArgumentException('Invalid or duplicate invoice row');
            }
            $seen[$invoice] = true;
            if (!isset($managerByVve[$vve])) continue;
            $manager = $managerByVve[$vve];
            $totals[$manager] = ($totals[$manager] ?? 0) + $amount;
        }
        ksort($totals);
        return $totals;
    }
}
