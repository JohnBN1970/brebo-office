<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Compatibility boundary for calculation-level update access.
 */
interface CalculationAccessGatewayInterface {

  public function assertCanEditWorkbench(int $calculationId, int $actorId): void;

}
