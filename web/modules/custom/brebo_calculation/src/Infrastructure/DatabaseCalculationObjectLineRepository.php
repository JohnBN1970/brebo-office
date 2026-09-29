<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationObjectLineRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationObjectLineRepository implements CalculationObjectLineRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function updateObjectRow(int $calculationId, string $version, int $rowId, array $values): void {
    $supported = [];
    foreach ($values as $field => $value) {
      if ($this->database->schema()->fieldExists('brebo_calculation_row_domain', $field)) {
        $supported[$field] = $value;
      }
    }
    $this->database->update('brebo_calculation_row_domain')
      ->fields($supported)
      ->condition('row_id', $rowId)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute();
  }
}
