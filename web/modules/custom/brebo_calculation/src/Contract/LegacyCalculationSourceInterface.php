<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Read-only source for legacy calculation data during migration/reconciliation.
 */
interface LegacyCalculationSourceInterface {

  /**
   * @return array{
   *   components:array<int,array{id:int,code:string,label:string,sequence:int}>,
   *   elements:array<int,array{id:int,component_id:int,zone_id:int,code:string,label:string,sequence:int}>,
   *   lines:array<int,array{
   *     id:int,element_id:int,line_type:string,post_type:string,category:string,
   *     quantity:float,actual_quantity:?float,unit_price:float,description:string,
   *     unit:string,sequence:int
   *   }>
   * }
   */
  public function load(int $calculationId): array;

}
