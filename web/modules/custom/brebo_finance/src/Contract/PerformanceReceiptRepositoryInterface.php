<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface PerformanceReceiptRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function commitmentLineContext(int $commitmentLineId): ?array;

  /** @return array<string,mixed>|null */
  public function receipt(int $receiptId): ?array;

  public function registeredAmount(int $commitmentLineId): string;

  /** @param callable():mixed $callback */
  public function transactional(callable $callback): mixed;

  /** @param array<string,mixed> $values */
  public function insertReceipt(array $values): int;

  /** @param array<string,mixed> $values */
  public function updateReceipt(int $receiptId, array $values): void;

  /** @param array<string,mixed> $values */
  public function insertAudit(array $values): void;

}
