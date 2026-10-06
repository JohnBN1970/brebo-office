<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationFactStoreInterface {

  public function currentTime(): int;

  /** @param array<string,mixed> $values */
  public function insertFact(array $values): void;

  /** @param array<string,mixed> $values */
  public function insertTakeoff(array $values): void;

}
