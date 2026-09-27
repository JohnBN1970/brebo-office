<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/** Validates nested workspace resource ownership. */
final class CalculationWorkspaceResourceGuard {

  public function __construct(
    private readonly Connection $database,
  ) {}

  public function assertRecipeInstance(int $calculationId, int $recipeInstanceId): void {
    $exists = $this->database->select('brebo_calculation_recipe_instance', 'i')
      ->condition('id', $recipeInstanceId)
      ->condition('calculation_id', $calculationId)
      ->countQuery()
      ->execute()
      ->fetchField();
    if (!(int) $exists) {
      throw new \InvalidArgumentException('Recipe instance does not belong to this calculation.');
    }
  }

  public function assertSubcalculation(int $calculationId, int $subcalculationId): void {
    $exists = $this->database->select('brebo_calculation_subcalculation', 's')
      ->condition('id', $subcalculationId)
      ->condition('calculation_id', $calculationId)
      ->countQuery()
      ->execute()
      ->fetchField();
    if (!(int) $exists) {
      throw new \InvalidArgumentException('Subcalculation does not belong to this calculation.');
    }
  }

  public function assertApplication(int $calculationId, int $subcalculationId, int $applicationId): void {
    $query = $this->database->select('brebo_calculation_subcalculation_application', 'a');
    $query->join('brebo_calculation_subcalculation', 's', 's.id = a.subcalculation_id');
    $query->condition('a.id', $applicationId)
      ->condition('a.subcalculation_id', $subcalculationId)
      ->condition('s.calculation_id', $calculationId);
    if (!(int) $query->countQuery()->execute()->fetchField()) {
      throw new \InvalidArgumentException('Application does not belong to this calculation subcalculation.');
    }
  }

  public function assertApplicationObject(int $calculationId, int $subcalculationId, int $applicationId, int $objectId): void {
    $query = $this->database->select('brebo_calculation_subcalculation_application_object', 'o');
    $query->join('brebo_calculation_subcalculation_application', 'a', 'a.id = o.application_id');
    $query->join('brebo_calculation_subcalculation', 's', 's.id = a.subcalculation_id');
    $query->condition('o.id', $objectId)
      ->condition('o.application_id', $applicationId)
      ->condition('a.subcalculation_id', $subcalculationId)
      ->condition('s.calculation_id', $calculationId);
    if (!(int) $query->countQuery()->execute()->fetchField()) {
      throw new \InvalidArgumentException('Application object does not belong to this calculation context.');
    }
  }

}
