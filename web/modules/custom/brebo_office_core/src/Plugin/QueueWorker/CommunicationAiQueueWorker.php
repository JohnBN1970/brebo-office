<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Blocks legacy direct AI queue items.
 *
 * @QueueWorker(
 *   id = "brebo_communication_ai",
 *   title = @Translation("BREBO communication AI (blocked)"),
 *   cron = {"time" = 1}
 * )
 */
final class CommunicationAiQueueWorker extends QueueWorkerBase {

  public function processItem($data): void {
    throw new \RuntimeException('Legacy AI-queue geblokkeerd: verwerking moet via de centrale BREBO Integration API lopen.');
  }

}
