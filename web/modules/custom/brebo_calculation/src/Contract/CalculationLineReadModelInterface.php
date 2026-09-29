<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Read-only view of BREBO calculation rows required by the domain result.
 */
interface CalculationLineReadModelInterface {

  /**
   * @param int[] $rowIds
   *
   * @return array<int,array{description:string,contract_quantity:float,actual_quantity:?float,unit:string,budget_hours:float,labour_rate:float}>
   */
  public function loadMany(array $rowIds, string $version): array;

}
