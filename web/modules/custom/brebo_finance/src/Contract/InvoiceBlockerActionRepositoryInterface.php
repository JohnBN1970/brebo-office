<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface InvoiceBlockerActionRepositoryInterface {

  public function ensureStorage(): void;

  public function activeIdForInvoiceLine(int $invoiceLineId): ?int;

  /** @param array<string,mixed> $fields */
  public function update(int $id, array $fields): void;

  /** @param array<string,mixed> $fields */
  public function create(array $fields): int;

  /** @return list<array<string,mixed>> */
  public function forProject(int $projectId): array;

  /** @return array<string,mixed> */
  public function get(int $id): array;

}
