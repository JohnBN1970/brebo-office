<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\SalesInvoiceReceivablesRepositoryInterface;

/** Reconciles Moneybird receivable state into existing BREBO sales invoices. */
final class SalesInvoiceReceivablesReconciler {

  private readonly VatCalculator $decimal;

  public function __construct(
    private readonly SalesInvoiceReceivablesRepositoryInterface $repository,
    private readonly SalesInvoiceReceivablesIntegrationClient $client,
    private readonly BillingControlManager $billingControlManager,
    private readonly ReceivablesReconciliationMonitor $monitor,
    ?VatCalculator $decimal = NULL,
  ) {
    $this->decimal = $decimal ?? new VatCalculator();
  }

  /** @return array{received:int,updated:int,unchanged:int,unmatched:int} */
  public function sync(): array {
    throw new \RuntimeException('Moneybird-to-Office receivables sync is disabled; payment status belongs to BREBO Office.');
  }

  private function status(string $state, string $paid, string $total, string $dueDate): string {
    $state = strtolower(trim($state));
    if (in_array($state, ['cancelled', 'canceled'], TRUE)) return 'cancelled';
    if (in_array($state, ['credited', 'credit_invoice'], TRUE)) return 'credited';
    if (in_array($state, ['disputed'], TRUE)) return 'disputed';
    if ($this->decimal->compare($total, '0') > 0 && $this->decimal->compare($paid, $total) >= 0) return 'paid';
    if ($state === 'late' || $state === 'overdue' || ($dueDate !== '' && $dueDate < date('Y-m-d') && $this->decimal->compare($paid, $total) < 0)) return 'overdue';
    if (in_array($state, ['draft', 'new'], TRUE)) return 'draft';
    return 'sent';
  }

  /** Normalizes a provider money value to the Finance four-decimal format. */
  private function money(string $value): string {
    return $this->decimal->add('0', trim($value));
  }

  private function date(mixed $value): ?string {
    $value = trim((string) ($value ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : NULL;
  }

}
