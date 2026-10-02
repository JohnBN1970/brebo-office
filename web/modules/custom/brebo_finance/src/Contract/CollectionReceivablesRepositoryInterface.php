<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for collection reconciliation state and invoice updates. */
interface CollectionReceivablesRepositoryInterface {

  /** @return array<string|int,mixed> */
  public function transferStates(): array;

  /** @return array<string,mixed>|null */
  public function invoice(int $invoiceId): ?array;

  public function updateInvoice(int $invoiceId, string $paidAmountIncVat, string $status, int $changedAt): void;

  /** @param array<string,mixed> $record */
  public function recordAudit(int $invoiceId, array $record): void;

}
