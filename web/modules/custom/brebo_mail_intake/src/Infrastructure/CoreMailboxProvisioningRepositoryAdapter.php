<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Brebo\Mail\Contract\MailboxProvisioningRepositoryInterface;
use Drupal\Core\Database\Connection;

final class CoreMailboxProvisioningRepositoryAdapter implements MailboxProvisioningRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function createMailbox(string $machineName, string $label, string $address, string $privacyType = 'functional', int $ownerUid = 0): int {
    $now = time();
    return (int) $this->database->insert('brebo_mailbox')->fields([
      'machine_name' => $machineName,
      'label' => $label,
      'address' => $address,
      'privacy_type' => $privacyType,
      'owner_uid' => $ownerUid,
      'active' => 1,
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }

  public function mailboxExists(int $mailboxId): bool {
    return (bool) $this->database->select('brebo_mailbox', 'm')
      ->condition('id', $mailboxId)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function addressInUse(string $address): bool {
    if ((bool) $this->database->select('brebo_mailbox', 'm')
      ->condition('address', $address)
      ->countQuery()
      ->execute()
      ->fetchField()) {
      return TRUE;
    }

    return (bool) $this->database->select('brebo_mailbox_alias', 'a')
      ->condition('address', $address)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function createAlias(int $mailboxId, string $address): int {
    return (int) $this->database->insert('brebo_mailbox_alias')->fields([
      'mailbox_id' => $mailboxId,
      'address' => $address,
      'active' => 1,
      'created' => time(),
    ])->execute();
  }

  public function aliases(int $mailboxId): array {
    return array_values(array_map('get_object_vars', $this->database->select('brebo_mailbox_alias', 'a')
      ->fields('a')
      ->condition('mailbox_id', $mailboxId)
      ->orderBy('address')
      ->execute()
      ->fetchAll()));
  }
}
