<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface FailureCostRepositoryInterface {
  /** @param array<string,mixed> $fields */
  public function create(array $fields): int;
  /** @return array<string,mixed>|null */
  public function load(int $id): ?array;
  /** @param array<string,mixed> $fields */
  public function update(int $id,array $fields): void;
  /** @param array<string,mixed> $fields */
  public function audit(array $fields): void;
}
