<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

use RuntimeException;

/**
 * Read-only Office supplier recipient resolver. The injected callbacks MUST use
 * Office-owned invoice and CRM tables, never Moneybird or Drupal master data.
 * This class does not activate a payment gateway or execute SQL.
 */
final class OfficeSupplierRecipientResolver {
    /** @var \Closure(int):?array */
    private \Closure $invoiceSupplier;
    /** @var \Closure(int):array */
    private \Closure $supplierAccounts;

    public function __construct(callable $invoiceSupplier, callable $supplierAccounts) {
        $this->invoiceSupplier = \Closure::fromCallable($invoiceSupplier);
        $this->supplierAccounts = \Closure::fromCallable($supplierAccounts);
    }

    public function snapshot(int $invoiceId, int $now): array {
        if ($invoiceId <= 0) throw new RuntimeException('Invalid Office invoice ID.');
        $invoice = ($this->invoiceSupplier)($invoiceId);
        if (!is_array($invoice) || (int) ($invoice['relation_id'] ?? 0) <= 0
            || ($invoice['source'] ?? '') !== 'office'
            || ($invoice['invoice_type'] ?? '') !== 'purchase') {
            throw new RuntimeException('Payment blocked: Office purchase invoice supplier unresolved.');
        }
        $relationId = (int) $invoice['relation_id'];
        $accounts = ($this->supplierAccounts)($relationId);
        $active = array_values(array_filter($accounts, static fn($row): bool =>
            is_array($row) && (int) ($row['relation_id'] ?? 0) === $relationId
            && ($row['account_type'] ?? '') === 'regular'
            && ($row['status'] ?? '') === 'active'));
        if (count($active) !== 1) {
            throw new RuntimeException('Payment blocked: exactly one active Office supplier account required.');
        }
        return OfficeSupplierRecipientPolicy::verifiedSnapshot($active[0], $invoiceId, $now);
    }

    public function unchanged(int $invoiceId, string $expectedHash, int $now): bool {
        if (!preg_match('/^[a-f0-9]{64}$/', $expectedHash)) return false;
        try {
            $current = $this->snapshot($invoiceId, $now);
            return hash_equals($expectedHash, (string) $current['recipient_hash']);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
