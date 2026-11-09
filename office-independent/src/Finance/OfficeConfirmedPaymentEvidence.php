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
      if ((int) ($row['invoice_id'] ?? 0) !== $invoiceId) {
        continue;
      }
      $id = trim((string) ($row['allocation_id'] ?? ''));
      $bankId = trim((string) ($row['bank_mutation_id'] ?? ''));
      $cents = $row['amount_cents'] ?? null;
      if ($id === '' || $bankId === '' || !is_int($cents) || $cents <= 0) {
        throw new \InvalidArgumentException('Invalid payment allocation evidence.');
      }
      if (isset($seen[$id])) {
        throw new \UnexpectedValueException('Duplicate payment allocation identifier.');
      }
      $seen[$id] = true;
      if (($row['status'] ?? '') !== 'confirmed' || ($row['match_status'] ?? '') !== 'unique') {
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
