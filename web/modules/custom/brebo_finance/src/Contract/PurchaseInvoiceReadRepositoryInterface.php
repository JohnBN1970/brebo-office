<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Read boundary for purchase-invoice screens and action guards. */
interface PurchaseInvoiceReadRepositoryInterface {

  /** @return list<array<string,mixed>> */
  public function invoices(): array;

  /** @return array<string,mixed>|null */
  public function invoice(int $invoiceId): ?array;

  /** @return list<array<string,mixed>> */
  public function lines(int $invoiceId): array;

  /** @return list<array<string,mixed>> */
  public function commitmentLinesForProject(int $projectNid): array;

  /** @return array<string,mixed>|null */
  public function invoiceLine(int $invoiceId, int $lineId): ?array;

  public function receiptBelongsToInvoice(int $invoiceId, int $receiptId): bool;

  public function releaseBelongsToInvoice(int $invoiceId, int $releaseId): bool;

}
