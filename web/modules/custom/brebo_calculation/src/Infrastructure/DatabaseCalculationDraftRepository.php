<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationDraftRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationDraftRepository implements CalculationDraftRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function latestVersion(int $calculationId): ?string {
    $value = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['version'])
      ->condition('calculation_id', $calculationId)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return is_string($value) && $value !== '' ? $value : NULL;
  }

  public function insertVersion(array $values): void {
    $this->database->insert('brebo_calculation_version')->fields($values)->execute();
  }
}
