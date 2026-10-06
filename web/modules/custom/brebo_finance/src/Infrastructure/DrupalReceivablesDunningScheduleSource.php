<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ReceivablesDunningScheduleSourceInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

final class DrupalReceivablesDunningScheduleSource implements ReceivablesDunningScheduleSourceInterface {

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  public function scheduleSettings(): array {
    $config = $this->configFactory->get('brebo_finance.receivables');
    return [
      'reminder' => $config->get('dunning.reminder_after_days'),
      'demand' => $config->get('dunning.demand_after_days'),
      'final_notice' => $config->get('dunning.final_notice_after_days'),
      'collection_ready' => $config->get('dunning.collection_ready_after_days'),
    ];
  }

}
