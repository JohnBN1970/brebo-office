<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface FinancialProjectLedgerRepositoryInterface {
  /** @return array<string,list<array<string,mixed>>> */
  public function projectLedger(int $projectId): array;
}
