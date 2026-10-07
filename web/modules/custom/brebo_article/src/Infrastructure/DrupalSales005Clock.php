<?php

declare(strict_types=1);

namespace Drupal\brebo_article\Infrastructure;

use Drupal\brebo_article\Contract\Sales005ClockInterface;
use Drupal\Component\Datetime\TimeInterface;

/** Drupal clock adapter for SALES005 imports. */
final class DrupalSales005Clock implements Sales005ClockInterface {

  public function __construct(private readonly TimeInterface $time) {}

  public function now(): int {
    return $this->time->getRequestTime();
  }

}
