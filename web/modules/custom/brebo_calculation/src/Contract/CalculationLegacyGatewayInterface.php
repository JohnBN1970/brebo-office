<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/** Compatibility boundary for legacy calculation aggregate writes. */
interface CalculationLegacyGatewayInterface {

  public function markEstablished(int $calculationId): void;

}
