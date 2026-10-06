<?php

declare(strict_types=1);

namespace Drupal\brebo_measure\Contract;

interface MeasureStorageInterface {

  public function currentTime(): int;

  /** @param array<string,mixed> $values */
  public function insert(string $table, array $values): int;

  /** @return array<string,mixed>|null */
  public function load(string $table, int $id): ?array;

  public function maxVersionForAssignment(int $assignmentId): int;

}
