<?php
declare(strict_types=1);

namespace Brebo\Office\Finance;

/** Pure Office-owned payment status projection; never reads Moneybird invoices. */
final class OfficeInvoicePaymentStatus {
  /**
   * Amounts are integer cents; credit notes reduce the payable balance.
   * Only confirmed, uniquely matched bank evidence may be counted as paid.
   *
   * @return array{status:string,outstanding_cents:int,paid_cents:int}
   */
  public static function project(int $totalCents, int $creditedCents, int $paidCents, string $dueDate, string $asOfDate, bool $cancelled = false): array {
    if ($totalCents < 0 || $creditedCents < 0 || $paidCents < 0 || $creditedCents > $totalCents || $paidCents > $totalCents - $creditedCents) {
      throw new \InvalidArgumentException('Invalid invoice, credit or confirmed payment amounts.');
    }
    foreach ([$dueDate, $asOfDate] as $date) {
      $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
      if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new \InvalidArgumentException('Invalid date. Expected YYYY-MM-DD.');
      }
    }
    $outstanding = $totalCents - $creditedCents - $paidCents;
    $status = $cancelled ? 'cancelled' : ($outstanding === 0 ? ($creditedCents === $totalCents ? 'credited' : 'paid') : ($dueDate < $asOfDate ? 'overdue' : ($paidCents > 0 || $creditedCents > 0 ? 'partial' : 'open')));
    return ['status' => $status, 'outstanding_cents' => $outstanding, 'paid_cents' => $paidCents];
  }
}
