<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationParametersRepositoryInterface;
use Drupal\Core\Database\Connection;

/**
 * Drupal database adapter for calculation parameter persistence.
 */
final class DatabaseCalculationParametersRepository implements CalculationParametersRepositoryInterface {

  public function __construct(
    private readonly Connection $database,
  ) {}

  public function loadDraft(int $calculationId, string $version): ?array {
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()
      ->fetchAssoc();

    return $row ?: NULL;
  }

  public function compareAndSwap(
    int $calculationId,
    string $version,
    string $expectedContentHash,
    array $values,
  ): bool {
    $updated = $this->database->update('brebo_calculation_version')
      ->fields($values)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('status', 'draft')
      ->isNull('locked_at')
      ->condition('content_hash', $expectedContentHash)
      ->execute();

    return $updated === 1;
  }

}
