<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use InvalidArgumentException;

/**
 * Finance-to-CRM reporting adapter. Input rows must come from authorized,
 * posted sales invoices with a verified Office relation mapping.
 * No direct Moneybird or legacy Drupal DB access is assumed here.
 */
final class OfficeCrmFinanceRevenueReport {
    public static function build(array $financeInvoices, array $managementLinks, int $year): array {
        if ($year < 2000 || $year > 2100) throw new InvalidArgumentException('Invalid reporting year');
        $rows = [];
        foreach ($financeInvoices as $invoice) {
            $date = $invoice['invoice_date'] ?? null;
            if (!is_string($date) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D', $date)) {
                throw new InvalidArgumentException('Missing verified invoice date');
            }
            if ((int)substr($date, 0, 4) !== $year) continue;
            $status = $invoice['status'] ?? null;
            if (!in_array($status, ['sent', 'paid', 'overdue', 'partially_paid'], true)) continue;
            $rows[] = [
                'invoice_id' => $invoice['invoice_id'] ?? null,
                'customer_relation_id' => $invoice['customer_relation_id'] ?? null,
                'net_revenue_cents' => $invoice['net_revenue_cents'] ?? null,
            ];
        }
        return OfficeCrmManagerRevenue::totals($rows, $managementLinks, sprintf('%04d-12-31', $year));
    }
}
