<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Configuration boundary for receivables escalation timing. */
interface ReceivablesScheduleSourceInterface {

  /** @return array{reminder:int,demand:int,final_notice:int,collection_ready:int} */
  public function schedule(): array;

}
