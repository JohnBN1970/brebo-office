<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

interface MailIntakeQueueInterface {

  /** @param array<string,mixed> $item */
  public function enqueue(array $item): void;

  public function pendingCount(): int;

}
