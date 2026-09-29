<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationIdentityRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationIdentityRepository implements CalculationIdentityRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function structureIdentityExists(int $calculationId, string $version, int $candidateId): bool {
    return (bool) $this->database->select('brebo_calculation_structure', 's')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('node_key', ['group_' . $candidateId, 'paragraph_' . $candidateId], 'IN')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function rowIdentityExists(int $rowId): bool {
    return (bool) $this->database->select('brebo_calculation_row_domain', 'r')
      ->condition('row_id', $rowId)
      ->countQuery()
      ->execute()
      ->fetchField();
  }
}
