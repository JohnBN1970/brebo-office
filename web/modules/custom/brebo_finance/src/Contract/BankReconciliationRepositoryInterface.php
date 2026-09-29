<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface BankReconciliationRepositoryInterface {
  public function ensureStorage(): void;
  /** @return array<string,mixed>|null */
  public function existing(string $provider,string $transactionId): ?array;
  /** @return list<array<string,mixed>> */
  public function batchItemsByEndToEndId(string $endToEndId,array $batchStatuses): array;
  public function invoiceMoneybirdId(int $invoiceId): string;
  /** @param array<string,mixed> $fields */
  public function insert(array $fields): bool;
}
