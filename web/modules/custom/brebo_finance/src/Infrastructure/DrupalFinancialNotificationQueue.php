<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinancialNotificationQueueInterface;
use Drupal\Core\Queue\QueueFactory;

/** Drupal queue adapter for financial notification delivery. */
final class DrupalFinancialNotificationQueue implements FinancialNotificationQueueInterface {

  private const QUEUE_NAME = 'brebo_finance_notification_delivery';

  public function __construct(private readonly QueueFactory $queueFactory) {}

  public function enqueue(int $outboxId): void {
    $this->queueFactory->get(self::QUEUE_NAME)->createItem(['outbox_id' => $outboxId]);
  }

}
