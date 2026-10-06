<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

/** Mailbox capability checks for role-based and owner-only mailboxes. */
final class MailboxAccessPolicy {

  public function __construct(private readonly MailboxRepository $mailboxes) {}

  /**
   * @param string[] $roles
   */
  public function allowed(
    int $userId,
    array $roles,
    bool $siteAdmin,
    int $mailboxId,
    string $capability = 'view',
  ): bool {
    if ($siteAdmin) {
      return TRUE;
    }

    $mailbox = $this->mailboxes->load($mailboxId);
    if (!$mailbox || empty($mailbox['active'])) {
      return FALSE;
    }

    if (($mailbox['privacy_type'] ?? 'functional') === 'personal') {
      return (int) ($mailbox['owner_uid'] ?? 0) > 0
        && (int) ($mailbox['owner_uid'] ?? 0) === $userId;
    }

    $allowedRoles = $this->mailboxes->allowedRoles($mailboxId, $capability);
    if ($allowedRoles === []) {
      return FALSE;
    }

    return array_intersect($allowedRoles, $roles) !== [];
  }

}
