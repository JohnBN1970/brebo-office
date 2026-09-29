<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Read-only view of legacy calculation lines required by the domain result.
 */
interface CalculationLineReadModelInterface {

  /**
   * @param int[] $lineIds
   *
   * @return array<int,array{description:string,contract_quantity:float,actual_quantity:?float,unit:string}>
   */
  public function loadMany(array $lineIds): array;

}
