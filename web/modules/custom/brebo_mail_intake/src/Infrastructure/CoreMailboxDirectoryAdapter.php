<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Brebo\Mail\Contract\MailboxDirectoryInterface;
use Drupal\brebo_mail_intake\Contract\MailboxStorageRepositoryInterface;

final class CoreMailboxDirectoryAdapter implements MailboxDirectoryInterface {

  public function __construct(private readonly MailboxStorageRepositoryInterface $mailboxes) {}

  public function activeAddresses(): array {
    return array_values(array_filter(array_map(
      static fn(array $mailbox): string => mb_strtolower(trim((string) ($mailbox['address'] ?? ''))),
      $this->mailboxes->activeMailboxes(),
    )));
  }
}
