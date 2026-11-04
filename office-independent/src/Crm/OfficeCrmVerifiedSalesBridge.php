<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use InvalidArgumentException;

/**
 * Fail-closed adapter for verified Office Finance sales-invoice exports.
 * Caller must authenticate, authorize and map the legal customer to CRM.
 * Purchase invoices and sales drafts are explicitly unsupported.
 */
final class OfficeCrmVerifiedSalesBridge {
    public static function normalize(array $rows): array {
        $normalized = [];
        $seen = [];
        foreach ($rows as $row) {
            if (($row['source'] ?? null) !== 'office_finance_sales_invoice'
                || ($row['mapping_verified'] ?? null) !== true
                || ($row['authorized'] ?? null) !== true
                || ($row['currency'] ?? null) !== 'EUR') {
                throw new InvalidArgumentException('Unverified sales invoice source or relation mapping');
            }
            $id = $row['invoice_id'] ?? null;
            $relationId = $row['customer_relation_id'] ?? null;
            $cents = $row['net_revenue_cents'] ?? null;
            $date = $row['invoice_date'] ?? null;
            if ((!is_string($id) && !is_int($id)) || (string)$id === ''
                || !is_int($relationId) || $relationId < 1 || !is_int($cents)
                || !is_string($date) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D', $date)
                || !checkdate((int)substr($date,5,2), (int)substr($date,8,2), (int)substr($date,0,4))
                || isset($seen[(string)$id])) {
                throw new InvalidArgumentException('Invalid or duplicate sales invoice');
            }
            $seen[(string)$id] = true;
            $normalized[] = [
                'invoice_id'=>(string)$id,
                'customer_relation_id'=>$relationId,
                'net_revenue_cents'=>$cents,
                'invoice_date'=>$date,
                'status'=>$row['status'] ?? null,
            ];
        }
        return $normalized;
    }

    public static function report(array $rows, array $managementLinks, int $year): array {
        return OfficeCrmFinanceRevenueReport::build(self::normalize($rows), $managementLinks, $year);
    }
}
