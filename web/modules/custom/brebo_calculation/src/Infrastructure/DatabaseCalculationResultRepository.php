<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationResultRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationResultRepository implements CalculationResultRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function version(int $calculationId, ?string $versionName = NULL): ?array {
    $query = $this->database->select('brebo_calculation_version', 'v')->fields('v')
      ->condition('calculation_id', $calculationId);
    if ($versionName !== NULL) {
      $query->condition('version', $versionName);
    }
    else {
      $query->orderBy('id', 'DESC')->range(0, 1);
    }
    $row = $query->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function snapshotPayload(int $calculationId, string $version): ?string {
    $payload = $this->database->select('brebo_calculation_snapshot', 's')
      ->fields('s', ['payload'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchField();
    return is_string($payload) && $payload !== '' ? $payload : NULL;
  }

  public function rows(int $calculationId, string $version): array {
    return $this->database->select('brebo_calculation_row_domain', 'r')->fields('r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->orderBy('row_id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function recipeInstances(int $calculationId, string $version): array {
    return $this->database->select('brebo_calculation_recipe_instance', 'i')->fields('i')
      ->condition('calculation_id', $calculationId)
      ->condition('calculation_version', $version)
      ->orderBy('sort_order')->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function recipeLines(array $instanceIds): array {
    if ($instanceIds === []) {
      return [];
    }
    return $this->database->select('brebo_calculation_recipe_instance_line', 'l')->fields('l')
      ->condition('recipe_instance_id', $instanceIds, 'IN')
      ->orderBy('recipe_instance_id')->orderBy('sort_order')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

}
