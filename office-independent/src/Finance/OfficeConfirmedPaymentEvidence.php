<?php
declare(strict_types=1);

namespace Brebo\Office\Finance;

/** Reduces Office-approved, uniquely matched bank evidence to paid cents. */
final class OfficeConfirmedPaymentEvidence {
  /**
   * @param list<array<string,mixed>> $allocations
   * @return int Confirmed allocated cents, excluding ambiguous and duplicate bank evidence.
   */
  public static function sumForInvoice(int $invoiceId, array $allocations): int {
    if ($invoiceId <= 0) {
      throw new \InvalidArgumentException('Invoice id must be positive.');
    }
    $sum = 0;
    $seen = [];
    foreach ($allocations as $row) {
      if (!is_array($row)) {
        throw new \InvalidArgumentException('Invalid allocation record.');
      }
      $rowInvoiceId = $row['invoice_id'] ?? null;
      $id = $row['allocation_id'] ?? null;
      $bankId = $row['bank_mutation_id'] ?? null;
      $cents = $row['amount_cents'] ?? null;
      if (!is_int($rowInvoiceId) || $rowInvoiceId <= 0 || !is_string($id) || trim($id) === '' || !is_string($bankId) || trim($bankId) === '' || !is_int($cents) || $cents <= 0) {
        throw new \InvalidArgumentException('Invalid payment allocation evidence.');
      }
      // Check allocation identity across the entire evidence set, not just one invoice.
      // Multiple allocations from one bank mutation remain valid for split payments.
      if (isset($seen[$id])) {
        throw new \UnexpectedValueException('Duplicate payment allocation identifier.');
      }
      $seen[$id] = true;
      if ($rowInvoiceId !== $invoiceId || ($row['status'] ?? '') !== 'confirmed' || ($row['match_status'] ?? '') !== 'unique') {
        continue;
      }
      if ($sum > PHP_INT_MAX - $cents) {
        throw new \OverflowException('Confirmed payment sum overflow.');
      }
      $sum += $cents;
    }
    return $sum;
  }
}
