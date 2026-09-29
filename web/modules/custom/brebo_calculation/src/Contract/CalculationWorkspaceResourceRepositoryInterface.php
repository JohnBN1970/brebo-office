<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationWorkspaceResourceRepositoryInterface {
  public function recipeInstanceBelongsToCalculation(int $calculationId, int $recipeInstanceId): bool;
  public function subcalculationBelongsToCalculation(int $calculationId, int $subcalculationId): bool;
  public function applicationBelongsToContext(int $calculationId, int $subcalculationId, int $applicationId): bool;
  public function applicationObjectBelongsToContext(int $calculationId, int $subcalculationId, int $applicationId, int $objectId): bool;
}
