<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface ReceivablesDunningScheduleSourceInterface {

  /** @return array{reminder:mixed,demand:mixed,final_notice:mixed,collection_ready:mixed} */
  public function scheduleSettings(): array;

}
