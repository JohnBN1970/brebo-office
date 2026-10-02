<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface ProjectFinancialPositionRepositoryInterface {
  /** @return array<string,string> */
  public function values(int $projectNid): array;
  public function sourceStateHash(int $projectNid): string;
  /** @param array<string,mixed> $fields */
  public function createSnapshot(array $fields): int;
}
