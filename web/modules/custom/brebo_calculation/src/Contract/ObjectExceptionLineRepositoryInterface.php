<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface ObjectExceptionLineRepositoryInterface {
  /** @return array<string,mixed>|null */
  public function editableContext(int $applicationObjectId): ?array;
  /** @param array<string,mixed> $values */
  public function insertLine(array $values): int;
  public function markApplicationObjectException(int $applicationObjectId): void;
  /** @return list<array<string,mixed>> */
  public function lines(int $applicationObjectId): array;
  public function nextSortOrder(int $applicationObjectId): int;
}
