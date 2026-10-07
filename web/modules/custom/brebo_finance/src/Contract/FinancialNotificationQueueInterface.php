<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Queue boundary for financial notification delivery. */
interface FinancialNotificationQueueInterface {

  public function enqueue(int $outboxId): void;

}
