<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationWorkspaceReadRepositoryInterface;
use Drupal\Core\Database\Connection;

/**
 * Drupal database adapter for the calculation workspace read model.
 */
final class DatabaseCalculationWorkspaceReadRepository implements CalculationWorkspaceReadRepositoryInterface {

  public function __construct(
    private readonly Connection $database,
  ) {}

  public function latestVersion(int $calculationId): ?array {
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return $row ?: NULL;
  }

  public function structure(int $calculationId, string $version): array {
    return $this->database->select('brebo_calculation_structure', 's')
      ->fields('s')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->orderBy('sort_order')
      ->orderBy('depth')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function rows(int $calculationId, string $version): array {
    return $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->orderBy('paragraph_key')
      ->orderBy('sort_order')
      ->orderBy('row_id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function recipes(int $calculationId, string $version): array {
    return $this->database->select('brebo_calculation_recipe_instance', 'i')
      ->fields('i')
      ->condition('calculation_id', $calculationId)
      ->condition('calculation_version', $version)
      ->orderBy('paragraph_key')
      ->orderBy('sort_order')
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function subcalculations(int $calculationId, string $version): array {
    return $this->database->select('brebo_calculation_subcalculation', 's')
      ->fields('s')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->orderBy('label')
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

}
