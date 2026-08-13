<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Session\AccountInterface;

/** Role-based mailbox capability checks. */
final class MailboxAccessPolicy {

  public function __construct(private readonly MailboxRepository $mailboxes) {}

  public function allowed(AccountInterface $account, int $mailboxId, string $capability = 'view'): bool {
    if ($account->hasPermission('administer site configuration')) {
      return TRUE;
    }

    $allowedRoles = $this->mailboxes->allowedRoles($mailboxId, $capability);
    if ($allowedRoles === []) {
      return FALSE;
    }

    return array_intersect($allowedRoles, $account->getRoles()) !== [];
  }

}
