<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Read boundary for management control center finance/control facts. */
interface ManagementControlCenterReadRepositoryInterface {

  public function blockedPaymentValue(): float;

  public function overdueObligationCount(int $now): int;

  /** @return array{case_count:int, exposure:float} */
  public function criticalControllerCaseSummary(): array;

}
