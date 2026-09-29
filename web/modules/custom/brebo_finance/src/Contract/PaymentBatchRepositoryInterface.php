<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface PaymentBatchRepositoryInterface {
  public function ensureStorage(): void;
  /** @param callable():mixed $callback */
  public function transactional(callable $callback): mixed;
  /** @return array<string,mixed>|null */
  public function release(int $id): ?array;
  /** @return array<string,mixed>|null */
  public function invoice(int $id): ?array;
  /** @return array<string,mixed>|null */
  public function batch(int $id): ?array;
  /** @return list<array<string,mixed>> */
  public function items(int $batchId): array;
  public function releaseInOpenBatch(int $releaseId): bool;
  /** @param array<string,mixed> $values */
  public function insertBatch(array $values): int;
  /** @param array<string,mixed> $values */
  public function insertItem(array $values): int;
  /** @param array<string,mixed> $values */
  public function updateBatch(int $batchId,array $values): void;
  /** @param array<string,mixed> $values */
  public function updateItems(int $batchId,array $values): void;
  /** @param array<string,mixed> $values */
  public function insertAudit(array $values): void;
}
