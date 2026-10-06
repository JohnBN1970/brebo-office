<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationClockInterface {

  public function now(): int;

}
