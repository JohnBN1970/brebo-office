<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;

/** Durable, deduplicated outbox for financial decision notifications. */
final class FinancialNotificationOutbox {

  public function __construct(
    private readonly Connection $database,
    private readonly QueueFactory $queueFactory,
  ) {}

  /**
   * Creates one durable notification per exception/attention/recipient tuple.
   */
  public function enqueue(array $decision, string $attention, int $escalationLevel, int $dueAt): ?int {
    $this->ensureStorage();

    $recipient = $decision['assignment']['primary_candidate'] ?? NULL;
    $recipientUid = is_array($recipient) ? (int) ($recipient['uid'] ?? 0) : 0;
    $recipientMail = is_array($recipient) ? (string) ($recipient['mail'] ?? '') : '';
    $audience = $recipientUid > 0 ? 'user' : 'finance_escalation';
    $dedupeKey = hash('sha256', implode('|', [
      (string) $decision['exception_id'],
      $attention,
      (string) $recipientUid,
      $audience,
    ]));

    $existing = $this->database->select('brebo_finance_notification_outbox', 'o')
      ->fields('o', ['id'])
      ->condition('dedupe_key', $dedupeKey)
      ->execute()
      ->fetchField();
    if ($existing !== FALSE) return NULL;

    $payload = [
      'exception_id' => (int) $decision['exception_id'],
      'project_nid' => (int) $decision['project_nid'],
      'gate' => (string) $decision['gate'],
      'attention' => $attention,
      'escalation_level' => $escalationLevel,
      'due_at' => $dueAt,
      'exposure' => $decision['exposure'],
      'authorization' => $decision['authorization'],
      'assignment' => $decision['assignment'],
      'reason' => $decision['reason'] ?? NULL,
      'control_measure' => $decision['control_measure'] ?? NULL,
    ];

    $now = time();
    $id = (int) $this->database->insert('brebo_finance_notification_outbox')->fields([
      'project_nid' => (int) $decision['project_nid'],
      'exception_id' => (int) $decision['exception_id'],
      'attention' => $attention,
      'audience' => $audience,
      'recipient_uid' => $recipientUid > 0 ? $recipientUid : NULL,
      'recipient_mail' => $recipientMail !== '' ? $recipientMail : NULL,
      'channel' => 'in_app',
      'status' => 'queued',
      'attempts' => 0,
      'dedupe_key' => $dedupeKey,
      'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
      'last_error' => NULL,
      'created' => $now,
      'changed' => $now,
    ])->execute();

    $this->queueFactory->get('brebo_finance_notification_delivery')->createItem(['outbox_id' => $id]);
    return $id;
  }

  public function markReady(int $outboxId): void {
    $this->ensureStorage();
    $this->database->update('brebo_finance_notification_outbox')->fields([
      'status' => 'ready',
      'attempts' => $this->attempts($outboxId) + 1,
      'last_error' => NULL,
      'changed' => time(),
    ])->condition('id', $outboxId)->condition('status', ['queued', 'retry'], 'IN')->execute();
  }

  public function markRetry(int $outboxId, string $error): void {
    $this->ensureStorage();
    $this->database->update('brebo_finance_notification_outbox')->fields([
      'status' => 'retry',
      'attempts' => $this->attempts($outboxId) + 1,
      'last_error' => mb_substr($error, 0, 2000),
      'changed' => time(),
    ])->condition('id', $outboxId)->execute();
  }

  /** @return array<string, mixed>|null */
  public function load(int $outboxId): ?array {
    $this->ensureStorage();
    $row = $this->database->select('brebo_finance_notification_outbox', 'o')->fields('o')->condition('id', $outboxId)->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  private function attempts(int $outboxId): int {
    $value = $this->database->select('brebo_finance_notification_outbox', 'o')->fields('o', ['attempts'])->condition('id', $outboxId)->execute()->fetchField();
    return $value === FALSE ? 0 : (int) $value;
  }

  private function ensureStorage(): void {
    $schema = $this->database->schema();
    if ($schema->tableExists('brebo_finance_notification_outbox')) return;
    $schema->createTable('brebo_finance_notification_outbox', [
      'description' => 'Durable notification outbox for BREBO Finance decisions.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'project_nid' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'exception_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'attention' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
        'audience' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
        'recipient_uid' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
        'recipient_mail' => ['type' => 'varchar', 'length' => 254, 'not null' => FALSE],
        'channel' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
        'status' => ['type' => 'varchar', 'length' => 24, 'not null' => TRUE],
        'attempts' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 0],
        'dedupe_key' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
        'payload' => ['type' => 'text', 'size' => 'big', 'not null' => TRUE],
        'last_error' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
        'created' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'changed' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
      ],
      'primary key' => ['id'],
      'unique keys' => ['dedupe_key' => ['dedupe_key']],
      'indexes' => [
        'status_changed' => ['status', 'changed'],
        'recipient_status' => ['recipient_uid', 'status'],
        'project_exception' => ['project_nid', 'exception_id'],
      ],
    ]);
  }
}
