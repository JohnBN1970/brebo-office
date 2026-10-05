<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_mail_intake\Contract\MailboxStorageRepositoryInterface;

/** Canonical BREBO mailbox registry independent from provider folders. */
final class MailboxRepository {

  public function __construct(private readonly MailboxStorageRepositoryInterface $storage) {}

  /** @return array<int, array<string, mixed>> */
  public function all(): array {
    return $this->storage->mailboxes();
  }

  /** @return array<string, mixed>|null */
  public function load(int $mailboxId): ?array {
    return $this->storage->mailbox($mailboxId);
  }

  /** @return string[] */
  public function allowedRoles(int $mailboxId, string $capability = 'view'): array {
    return $this->storage->allowedRoles($mailboxId, $capability);
  }

}
