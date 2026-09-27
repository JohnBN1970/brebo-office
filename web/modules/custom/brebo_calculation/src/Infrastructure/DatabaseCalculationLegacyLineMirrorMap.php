<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationLegacyLineMirrorMapInterface;
use Drupal\Core\Database\Connection;

/**
 * Stores the temporary BREBO-row to legacy calc-line mirror mapping.
 */
final class DatabaseCalculationLegacyLineMirrorMap implements CalculationLegacyLineMirrorMapInterface {

  public function __construct(
    private readonly Connection $database,
  ) {}

  public function legacyLineId(int $calculationId, string $version, int $rowId): ?int {
    $legacyLineId = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r', ['calc_line_id'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('row_id', $rowId)
      ->execute()
      ->fetchField();

    return $legacyLineId ? (int) $legacyLineId : NULL;
  }

  public function attach(int $calculationId, string $version, int $rowId, int $legacyLineId): void {
    $this->database->update('brebo_calculation_row_domain')
      ->fields(['calc_line_id' => $legacyLineId])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('row_id', $rowId)
      ->execute();
  }

}
