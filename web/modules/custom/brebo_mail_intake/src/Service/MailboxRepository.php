<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Database\Connection;

/** Canonical BREBO mailbox registry independent from provider folders. */
final class MailboxRepository {

  public function __construct(private readonly Connection $database) {}

  /** @return array<int, array<string, mixed>> */
  public function all(): array {
    return array_values(array_map('get_object_vars', $this->database->select('brebo_mailbox', 'm')
      ->fields('m')
      ->orderBy('label')
      ->execute()
      ->fetchAll()));
  }

  /** @return array<string, mixed>|null */
  public function load(int $mailboxId): ?array {
    $row = $this->database->select('brebo_mailbox', 'm')
      ->fields('m')
      ->condition('id', $mailboxId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  /** @return string[] */
  public function allowedRoles(int $mailboxId, string $capability = 'view'): array {
    $roles = $this->database->select('brebo_mailbox_role', 'r')
      ->fields('r', ['role_id'])
      ->condition('mailbox_id', $mailboxId)
      ->condition('capability', $capability)
      ->execute()
      ->fetchCol();
    return array_values(array_unique(array_map('strval', $roles ?: [])));
  }

}
