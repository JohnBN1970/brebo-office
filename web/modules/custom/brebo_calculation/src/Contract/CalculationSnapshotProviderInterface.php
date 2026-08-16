<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Provides immutable, version-bound calculation snapshots to consumers.
 */
interface CalculationSnapshotProviderInterface {

  /**
   * Returns a normalized calculation snapshot for the requested version.
   *
   * The exact DTO/value-object implementation is introduced with the new
   * calculation domain model. Consumers must not read calculation node fields
   * directly once this contract is active.
   */
  public function snapshot(int $calculationId, ?string $version = NULL): object;

}
