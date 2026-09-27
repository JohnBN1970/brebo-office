<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/**
 * Generates framework-independent BREBO row identities safe for browser use.
 */
final class CalculationRowIdentityGenerator {

  private const MAX_SAFE_INTEGER = 9007199254740991;

  public function __construct(
    private readonly Connection $database,
  ) {}

  public function next(): int {
    for ($attempt = 0; $attempt < 10; $attempt++) {
      $rowId = random_int(1, self::MAX_SAFE_INTEGER);
      $exists = (bool) $this->database->select('brebo_calculation_row_domain', 'r')
        ->condition('row_id', $rowId)
        ->countQuery()
        ->execute()
        ->fetchField();
      if (!$exists) {
        return $rowId;
      }
    }

    throw new \RuntimeException('Unable to allocate a unique BREBO calculation row identity.');
  }

}
