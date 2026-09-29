<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface LegacyMigrationRepositoryInterface {
  public function versionExists(int $calculationId, string $version): bool;

  /** @param callable():void $callback */
  public function transactional(callable $callback): void;

  public function assertWritten(
    int $calculationId,
    string $version,
    int $structureCount,
    int $rowCount,
    string $hash,
  ): void;
}
