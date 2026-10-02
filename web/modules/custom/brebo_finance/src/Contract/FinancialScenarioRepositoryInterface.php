<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface FinancialScenarioRepositoryInterface {
  /** @param array<string,mixed> $fields */
  public function create(array $fields): int;
  /** @return array<string,mixed>|null */
  public function scenario(int $id): ?array;
  /** @return array<string,mixed>|null */
  public function forecast(int $id): ?array;
  /** @param array<string,mixed> $fields */
  public function createSnapshot(array $fields): int;
  /** @param array<string,mixed> $fields */
  public function updateScenario(int $id, array $fields): void;
}
