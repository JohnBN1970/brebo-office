<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationWorkspaceResourceRepositoryInterface;

/** Validates nested workspace resource ownership. */
final class CalculationWorkspaceResourceGuard {

  public function __construct(
    private readonly CalculationWorkspaceResourceRepositoryInterface $repository,
  ) {}

  public function assertRecipeInstance(int $calculationId, int $recipeInstanceId): void {
    if (!$this->repository->recipeInstanceBelongsToCalculation($calculationId, $recipeInstanceId)) {
      throw new \InvalidArgumentException('Recipe instance does not belong to this calculation.');
    }
  }

  public function assertSubcalculation(int $calculationId, int $subcalculationId): void {
    if (!$this->repository->subcalculationBelongsToCalculation($calculationId, $subcalculationId)) {
      throw new \InvalidArgumentException('Subcalculation does not belong to this calculation.');
    }
  }

  public function assertApplication(int $calculationId, int $subcalculationId, int $applicationId): void {
    if (!$this->repository->applicationBelongsToContext($calculationId, $subcalculationId, $applicationId)) {
      throw new \InvalidArgumentException('Application does not belong to this calculation subcalculation.');
    }
  }

  public function assertApplicationObject(int $calculationId, int $subcalculationId, int $applicationId, int $objectId): void {
    if (!$this->repository->applicationObjectBelongsToContext($calculationId, $subcalculationId, $applicationId, $objectId)) {
      throw new \InvalidArgumentException('Application object does not belong to this calculation context.');
    }
  }

}
