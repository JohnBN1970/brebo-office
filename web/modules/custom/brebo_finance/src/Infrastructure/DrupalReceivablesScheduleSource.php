<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ReceivablesScheduleSourceInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/** Drupal config adapter for receivables escalation timing. */
final class DrupalReceivablesScheduleSource implements ReceivablesScheduleSourceInterface {

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  public function schedule(): array {
    $config = $this->configFactory->get('brebo_finance.receivables');
    $read = static function (mixed $value, int $fallback): int {
      return is_numeric($value) ? max(0, (int) $value) : $fallback;
    };
    return [
      'reminder' => $read($config->get('dunning.reminder_after_days'), 3),
      'demand' => $read($config->get('dunning.demand_after_days'), 8),
      'final_notice' => $read($config->get('dunning.final_notice_after_days'), 15),
      'collection_ready' => $read($config->get('dunning.collection_ready_after_days'), 22),
    ];
  }

}
