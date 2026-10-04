<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Source boundary for portfolio cash events used in liquidity projections. */
interface PortfolioLiquidityRepositoryInterface {

  public function cashEventSchemaAvailable(): bool;

  /** @param list<int> $projectIds
   *  @return list<array<string,mixed>>
   */
  public function events(array $projectIds, string $endDate, array $statuses): array;

}
