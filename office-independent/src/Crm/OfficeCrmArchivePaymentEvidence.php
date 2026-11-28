<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use PDO;

/** Internal read-only evidence; caller must authorize access. No customer status filter. */
final class OfficeCrmArchivePaymentEvidence
{
    public function __construct(private readonly PDO $db) {}

    public function allInvoicesPaid(int $relationId): bool
    {
        if ($relationId < 1) return false;
        $q = $this->db->prepare('SELECT invoice_number, status, gross_cents, paid_cents FROM office_finance_sales_invoice WHERE relation_id=?');
        $q->execute([$relationId]);
        $seen = false;
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            // Unknown states, drafts and unresolved reconciliation block archival.
            if ($row['status'] !== 'sent' || trim((string)$row['invoice_number']) === '') return false;
            if (!preg_match('/^[0-9]+$/D', (string)$row['gross_cents']) ||
                !preg_match('/^[0-9]+$/D', (string)$row['paid_cents'])) return false;
            if ((int)$row['gross_cents'] !== (int)$row['paid_cents']) return false;
            $seen = true;
        }
        // Absence of invoices is not evidence of payment.
        return $seen;
    }
}
