<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface PerformanceLocationRepositoryInterface {

  public function ensureStorage(): void;

  /** @param array<string,mixed> $fields */
  public function save(int $receiptId, array $fields, int $createdAt, int $createdBy): void;

  /** @return array<string,mixed>|null */
  public function forReceipt(int $receiptId): ?array;

}
