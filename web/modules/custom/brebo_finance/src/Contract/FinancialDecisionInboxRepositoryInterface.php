<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface FinancialDecisionInboxRepositoryInterface {

  /** @return list<array<string,mixed>> */
  public function pending(?int $projectId, int $now): array;

}
