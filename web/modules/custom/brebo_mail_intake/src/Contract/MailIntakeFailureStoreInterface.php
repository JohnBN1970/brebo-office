<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

interface MailIntakeFailureStoreInterface {

  /** @return array<string,array<string,mixed>> */
  public function all(): array;

  /** @param array<string,array<string,mixed>> $items */
  public function replace(array $items): void;

}
