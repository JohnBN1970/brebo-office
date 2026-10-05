<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for source-neutral purchase-invoice intake. */
interface PurchaseInvoiceIntakeRepositoryInterface {

  public function available(): bool;

  public function findBySourceHash(string $sourceHash): ?int;

  /** @return list<int> */
  public function findBySupplierInvoice(string $supplierRef, string $invoiceNumber): array;

  /** @param array<string,mixed> $invoiceFields
   *  @param list<array<string,mixed>> $lines
   */
  public function createInvoice(array $invoiceFields, array $lines): int;

  public function findDuplicate(?string $sourceHash, string $supplierRef, string $invoiceNumber): ?int;

  /** @param array<string,mixed> $audit */
  public function appendAuditIfAvailable(array $audit): void;

}
