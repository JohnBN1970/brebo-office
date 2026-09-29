<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationNormFeedbackRepositoryInterface {
  /** @param array<string,mixed> $values */
  public function insertObservation(array $values): int;

  /** @return array<string,mixed> */
  public function summary(string $domain, string $normKey): array;
}
