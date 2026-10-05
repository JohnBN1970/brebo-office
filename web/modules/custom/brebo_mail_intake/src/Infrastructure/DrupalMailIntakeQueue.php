<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\MailIntakeQueueInterface;
use Drupal\Core\Queue\QueueFactory;

final class DrupalMailIntakeQueue implements MailIntakeQueueInterface {

  private const QUEUE_NAME = 'brebo_mail_intake_process';

  public function __construct(private readonly QueueFactory $queueFactory) {}

  public function enqueue(array $item): void {
    $this->queueFactory->get(self::QUEUE_NAME, TRUE)->createItem($item);
  }

  public function pendingCount(): int {
    return $this->queueFactory->get(self::QUEUE_NAME, TRUE)->numberOfItems();
  }

}
