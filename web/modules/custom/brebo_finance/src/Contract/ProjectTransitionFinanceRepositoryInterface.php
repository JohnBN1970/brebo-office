<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Finance persistence needed to guard and audit project lifecycle transitions. */
interface ProjectTransitionFinanceRepositoryInterface {

  /** @return array{commitments:int,purchase_invoices:int,billing_instalments:int} */
  public function openAdministration(int $projectId): array;

  /** @param array<string,mixed> $fields */
  public function appendAudit(array $fields): void;

}
