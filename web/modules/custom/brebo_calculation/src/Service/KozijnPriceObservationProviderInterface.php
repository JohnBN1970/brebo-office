<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

/** Read contract for approved kozijn calibration observations. */
interface KozijnPriceObservationProviderInterface {

  /** @return array<int,array<string,mixed>> */
  public function approved(string $system, string $type, int $fields = 1): array;

}
