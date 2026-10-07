<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

/** Storage boundary for technical mail-intake failures. */
interface MailIntakeFailureStoreInterface {

  /** @return array<string, array<string, mixed>> */
  public function all(): array;

  /** @param array<string, array<string, mixed>> $items */
  public function save(array $items): void;

}
