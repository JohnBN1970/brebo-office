<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for the Moneybird purchase-invoice mirror. */
interface PurchaseInvoiceImportRepositoryInterface {

  /** @return array{id:int,source_hash:string,moneybird_id:string}|null */
  public function findByMoneybirdId(string $moneybirdId): ?array;

  /** @return array{id:int,source_hash:string,moneybird_id:string}|null */
  public function findBySupplierInvoice(?string $supplierRef, string $invoiceNumber): ?array;

  /** @param array<string,mixed> $fields */
  public function create(array $fields): int;

  /** @param array<string,mixed> $fields */
  public function update(int $invoiceId, array $fields): void;

  /** @return array<string,mixed>|null */
  public function paymentRecipientInvoice(int $invoiceId): ?array;

  /** @param array<string,mixed> $audit */
  public function appendAuditIfAvailable(array $audit): void;

}
