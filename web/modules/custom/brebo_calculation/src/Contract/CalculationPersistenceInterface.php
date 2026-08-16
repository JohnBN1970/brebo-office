<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

use Drupal\brebo_calculation\Domain\CalculationSnapshot;
use Drupal\brebo_calculation\Domain\CalculationVersion;
use Drupal\brebo_calculation\Domain\StructureNode;

/** Persistence boundary for the calculation domain. */
interface CalculationPersistenceInterface {

  public function saveVersion(int $calculationId, CalculationVersion $version): void;

  /** @param list<StructureNode> $nodes */
  public function replaceStructure(int $calculationId, string $version, array $nodes): void;

  /**
   * Persists additive domain data for one existing brebo_calc_line identity.
   *
   * @param array<string, mixed> $data
   */
  public function saveRowDomain(int $calculationId, string $version, int $calcLineId, array $data): void;

  public function saveSnapshot(CalculationSnapshot $snapshot, int $created, ?int $createdBy = NULL): void;

}
