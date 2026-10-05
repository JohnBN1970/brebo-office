<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_mail_intake\Contract\MailIntakeQueueInterface;
use Drupal\brebo_mail_intake\Source\GmailSourceAdapter;
use Drupal\brebo_mail_intake\Source\MailSourceAdapterInterface;

/**
 * Enqueues normalized mail so cron stays short and processing is resumable.
 */
final class MailIntakeQueueManager {

  public function __construct(
    private readonly MailIntakeQueueInterface $queue,
  ) {}

  public function enqueueSource(MailSourceAdapterInterface $adapter, string $mode = 'live'): int {
    if (!$adapter->isConfigured()) {
      return 0;
    }

    $count = 0;
    foreach ($adapter->messages() as $mail) {
      $this->queue->enqueue([
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

    $count = 0;
    foreach ($adapter->backfillMessages() as $mail) {
      $this->queue->enqueue([
        'mode' => 'backfill',
        'mail' => $mail,
      ]);
      $count++;
    }
    return $count;
  }

  public function pendingCount(): int {
    return $this->queue->pendingCount();
  }

}
