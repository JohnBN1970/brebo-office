<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for controlled purchase-invoice coding. */
interface PurchaseInvoiceCodingRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function invoice(int $invoiceId): ?array;

  /** @param array<string,mixed> $fields */
  public function updateInvoice(int $invoiceId, array $fields): void;

  /** @param array<string,mixed> $fields */
  public function updateInvoiceLines(int $invoiceId, array $fields): void;

  public function invoiceLineId(int $invoiceId, int $lineNumber): ?int;

  /** @param array<string,mixed> $fields */
  public function updateLine(int $lineId, array $fields): void;

  /** @param array<string,mixed> $fields */
  public function createLine(array $fields): int;

  public function invoiceOwnsLine(int $invoiceId, int $lineId): bool;

  /** @return array{id:int,commitment_id:int}|null */
  public function commitmentLineForProject(int $commitmentLineId, int $projectNid): ?array;

  /** @param array<string,mixed> $audit */
  public function appendAudit(array $audit): void;

}
