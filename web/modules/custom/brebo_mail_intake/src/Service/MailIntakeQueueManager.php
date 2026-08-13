<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Queue\QueueFactory;
use Drupal\brebo_mail_intake\Source\GmailSourceAdapter;
use Drupal\brebo_mail_intake\Source\MailSourceAdapterInterface;

/**
 * Enqueues normalized mail so cron stays short and processing is resumable.
 */
final class MailIntakeQueueManager {

  public const QUEUE_NAME = 'brebo_mail_intake_process';

  public function __construct(
    private readonly QueueFactory $queueFactory,
  ) {}

  public function enqueueSource(MailSourceAdapterInterface $adapter, string $mode = 'live'): int {
    if (!$adapter->isConfigured()) {
      return 0;
    }

    $queue = $this->queueFactory->get(self::QUEUE_NAME, TRUE);
    $count = 0;
    foreach ($adapter->messages() as $mail) {
      $queue->createItem([
        'mode' => $mode,
        'mail' => $mail,
      ]);
      $count++;
    }
    return $count;
  }

  public function enqueueGmailBackfill(GmailSourceAdapter $adapter): int {
    if (!$adapter->isConfigured() || $adapter->isBackfillComplete()) {
      return 0;
    }

    $maxPending = max(10, min(1000, (int) (getenv('BREBO_GMAIL_BACKFILL_MAX_PENDING') ?: 100)));
    if ($this->pendingCount() >= $maxPending) {
      return 0;
    }

    $queue = $this->queueFactory->get(self::QUEUE_NAME, TRUE);
    $count = 0;
    foreach ($adapter->backfillMessages() as $mail) {
      $queue->createItem([
        'mode' => 'backfill',
        'mail' => $mail,
      ]);
      $count++;
    }
    return $count;
  }

  public function pendingCount(): int {
    return $this->queueFactory->get(self::QUEUE_NAME, TRUE)->numberOfItems();
  }

}
