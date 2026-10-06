<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationFactStoreInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationFactStore implements CalculationFactStoreInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  public function currentTime(): int {
    return $this->time->getRequestTime();
  }

  public function insertFact(array $values): void {
    $this->database
      ->insert('brebo_calculation_fact')
      ->fields($values)
      ->execute();
  }

  public function insertTakeoff(array $values): void {
    $this->database
      ->insert('brebo_calculation_takeoff')
      ->fields($values)
      ->execute();
  }

}
