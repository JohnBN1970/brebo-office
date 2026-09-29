<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationReadinessRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationReadinessRepository implements CalculationReadinessRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function rows(int $calculationId, string $version): array {
    return $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function recipeLines(int $calculationId, string $version): array {
    $query = $this->database->select('brebo_calculation_recipe_instance_line', 'l');
    $query->join('brebo_calculation_recipe_instance', 'i', 'i.id = l.recipe_instance_id');
    $query->fields('l');
    $query->condition('i.calculation_id', $calculationId);
    $query->condition('i.calculation_version', $version);
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }
}
