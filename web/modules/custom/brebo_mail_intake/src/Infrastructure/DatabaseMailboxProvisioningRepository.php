<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\MailboxProvisioningRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseMailboxProvisioningRepository implements MailboxProvisioningRepositoryInterface {
  public function __construct(private readonly Connection $database) {}

  public function createMailbox(string $machineName, string $label, string $address, string $privacyType = 'functional', int $ownerUid = 0): int {
    $now = time();
    return (int) $this->database->insert('brebo_mailbox')->fields([
      'machine_name' => $machineName,
      'label' => $label,
      'address' => mb_strtolower(trim($address)),
      'privacy_type' => $privacyType,
      'owner_uid' => $ownerUid,
      'active' => 1,
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }

  public function mailboxIdByAddress(string $address): ?int {
    $id = $this->database->select('brebo_mailbox', 'm')->fields('m', ['id'])
      ->condition('address', mb_strtolower(trim($address)))->range(0, 1)->execute()->fetchField();
    return $id === FALSE ? NULL : (int) $id;
  }

  public function createAlias(int $mailboxId, string $address): int {
    return (int) $this->database->insert('brebo_mailbox_alias')->fields([
      'mailbox_id' => $mailboxId,
      'address' => mb_strtolower(trim($address)),
      'active' => 1,
      'created' => time(),
    ])->execute();
  }

  public function aliases(int $mailboxId): array {
    return array_values(array_map('get_object_vars', $this->database->select('brebo_mailbox_alias', 'a')
      ->fields('a')->condition('mailbox_id', $mailboxId)->orderBy('address')->execute()->fetchAll()));
  }
}
