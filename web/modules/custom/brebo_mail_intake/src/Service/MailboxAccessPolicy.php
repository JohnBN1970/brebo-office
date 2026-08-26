<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Session\AccountInterface;

/** Mailbox capability checks for role-based and owner-only mailboxes. */
final class MailboxAccessPolicy {

  public function __construct(private readonly MailboxRepository $mailboxes) {}

  public function allowed(AccountInterface $account, int $mailboxId, string $capability = 'view'): bool {
    if ($account->hasPermission('administer site configuration')) {
      return TRUE;
    }

    $mailbox = $this->mailboxes->load($mailboxId);
    if (!$mailbox || empty($mailbox['active'])) {
      return FALSE;
    }

    if (($mailbox['privacy_type'] ?? 'functional') === 'personal') {
      return (int) ($mailbox['owner_uid'] ?? 0) > 0
        && (int) ($mailbox['owner_uid'] ?? 0) === (int) $account->id();
    }

    $allowedRoles = $this->mailboxes->allowedRoles($mailboxId, $capability);
    if ($allowedRoles === []) {
      return FALSE;
    }

    return array_intersect($allowedRoles, $account->getRoles()) !== [];
  }

}
