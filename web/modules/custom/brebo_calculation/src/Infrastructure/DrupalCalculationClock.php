<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationClockInterface;
use Drupal\Component\Datetime\TimeInterface;

final class DrupalCalculationClock implements CalculationClockInterface {

  public function __construct(private readonly TimeInterface $time) {}

  public function now(): int {
    return $this->time->getCurrentTime();
  }

}
