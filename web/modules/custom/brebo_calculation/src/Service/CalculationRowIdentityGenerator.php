<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationIdentityRepositoryInterface;

/**
 * Generates framework-independent BREBO row identities safe for browser use.
 */
final class CalculationRowIdentityGenerator {

  private const MAX_SAFE_INTEGER = 9007199254740991;

  public function __construct(
    private readonly CalculationIdentityRepositoryInterface $repository,
  ) {}

  public function next(): int {
    for ($attempt = 0; $attempt < 10; $attempt++) {
      $rowId = random_int(1, self::MAX_SAFE_INTEGER);
      if (!$this->repository->rowIdentityExists($rowId)) {
        return $rowId;
      }
    }

    throw new \RuntimeException('Unable to allocate a unique BREBO calculation row identity.');
  }

}
