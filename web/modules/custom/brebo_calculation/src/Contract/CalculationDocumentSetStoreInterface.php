<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationDocumentSetStoreInterface {

  public function currentTime(): int;

  /** @param array<string,mixed> $values */
  public function createSet(array $values): int;

  /** @return array<int,array<string,mixed>> */
  public function projectDocuments(int $projectId): array;

  /** @param array<string,mixed> $values */
  public function createItem(array $values): int;

}
