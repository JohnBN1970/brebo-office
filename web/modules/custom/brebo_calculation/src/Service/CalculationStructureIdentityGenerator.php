<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/** Generates BREBO-owned calculation structure keys. */
final class CalculationStructureIdentityGenerator {

  private const MAX_SAFE_INTEGER = 9007199254740991;

  public function __construct(
    private readonly Connection $database,
  ) {}

  public function mainGroupKey(int $calculationId, string $version): string {
    return 'group_' . $this->nextId($calculationId, $version);
  }

  public function paragraphKey(int $calculationId, string $version): string {
    return 'paragraph_' . $this->nextId($calculationId, $version);
  }

  private function nextId(int $calculationId, string $version): int {
    for ($attempt = 0; $attempt < 10; $attempt++) {
      $id = random_int(1, self::MAX_SAFE_INTEGER);
      $exists = (bool) $this->database->select('brebo_calculation_structure', 's')
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->condition('node_key', ['group_' . $id, 'paragraph_' . $id], 'IN')
        ->countQuery()
        ->execute()
        ->fetchField();
      if (!$exists) {
        return $id;
      }
    }

    throw new \RuntimeException('Unable to allocate a unique BREBO calculation structure identity.');
  }

}
