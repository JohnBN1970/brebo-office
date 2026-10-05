<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Aggregate source boundary for the organisation-wide Finance dashboard. */
interface FinancialCommandCenterRepositoryInterface {

  /** @param list<int> $projectIds
   *  @return array<string,int|float>
   */
  public function portfolio(array $projectIds): array;

}
