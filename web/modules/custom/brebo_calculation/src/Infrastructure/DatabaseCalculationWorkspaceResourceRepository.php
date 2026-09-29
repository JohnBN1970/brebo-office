<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationWorkspaceResourceRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationWorkspaceResourceRepository implements CalculationWorkspaceResourceRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function recipeInstanceBelongsToCalculation(int $calculationId, int $recipeInstanceId): bool {
    return (bool) $this->database->select('brebo_calculation_recipe_instance', 'i')
      ->condition('id', $recipeInstanceId)
      ->condition('calculation_id', $calculationId)
      ->countQuery()->execute()->fetchField();
  }

  public function subcalculationBelongsToCalculation(int $calculationId, int $subcalculationId): bool {
    return (bool) $this->database->select('brebo_calculation_subcalculation', 's')
      ->condition('id', $subcalculationId)
      ->condition('calculation_id', $calculationId)
      ->countQuery()->execute()->fetchField();
  }

  public function applicationBelongsToContext(int $calculationId, int $subcalculationId, int $applicationId): bool {
    $query = $this->database->select('brebo_calculation_subcalculation_application', 'a');
    $query->join('brebo_calculation_subcalculation', 's', 's.id = a.subcalculation_id');
    $query->condition('a.id', $applicationId)
      ->condition('a.subcalculation_id', $subcalculationId)
      ->condition('s.calculation_id', $calculationId);
    return (bool) $query->countQuery()->execute()->fetchField();
  }

  public function applicationObjectBelongsToContext(int $calculationId, int $subcalculationId, int $applicationId, int $objectId): bool {
    $query = $this->database->select('brebo_calculation_subcalculation_application_object', 'o');
    $query->join('brebo_calculation_subcalculation_application', 'a', 'a.id = o.application_id');
    $query->join('brebo_calculation_subcalculation', 's', 's.id = a.subcalculation_id');
    $query->condition('o.id', $objectId)
      ->condition('o.application_id', $applicationId)
      ->condition('a.subcalculation_id', $subcalculationId)
      ->condition('s.calculation_id', $calculationId);
    return (bool) $query->countQuery()->execute()->fetchField();
  }
}
