<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\FinancialNotificationOutboxRepositoryInterface;
use Drupal\brebo_finance\Contract\FinancialNotificationQueueInterface;
use UnexpectedValueException;

/** Durable, deduplicated outbox for financial decision notifications. */
final class FinancialNotificationOutbox {

  public function __construct(
    private readonly FinancialNotificationOutboxRepositoryInterface $repository,
    private readonly FinancialNotificationQueueInterface $queue,
  ) {}

  public function enqueue(array $decision, string $attention, int $escalationLevel, int $dueAt): ?int {
    $this->repository->ensureStorage();
    $recipient = $decision['assignment']['primary_candidate'] ?? NULL;
    $recipientUid = is_array($recipient) ? (int) ($recipient['uid'] ?? 0) : 0;
    $recipientMail = is_array($recipient) ? (string) ($recipient['mail'] ?? '') : '';
    $audience = $recipientUid > 0 ? 'user' : 'finance_escalation';
    $dedupeKey = hash('sha256', implode('|', [(string) $decision['exception_id'], $attention, (string) $recipientUid, $audience]));

    if ($this->repository->existsByDedupeKey($dedupeKey)) return NULL;

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
    $id = $this->repository->create([
      'project_nid' => (int) $decision['project_nid'], 'exception_id' => (int) $decision['exception_id'],
      'attention' => $attention, 'audience' => $audience, 'recipient_uid' => $recipientUid > 0 ? $recipientUid : NULL,
      'recipient_mail' => $recipientMail !== '' ? $recipientMail : NULL, 'channel' => 'in_app', 'status' => 'queued',
      'attempts' => 0, 'dedupe_key' => $dedupeKey,
      'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
      'last_error' => NULL, 'created' => $now, 'changed' => $now,
    ]);
    $this->queue->enqueue($id);
    return $id;
  }

  public function markReady(int $outboxId): void {
    $this->repository->ensureStorage();
    $this->repository->markReady($outboxId, time());
  }

  public function markRetry(int $outboxId, string $error): void {
    $this->repository->ensureStorage();
    $this->repository->markRetry($outboxId, $error, time());
  }

  /** @return list<array<string, mixed>> */
  public function forUser(int $uid, bool $unreadOnly = TRUE): array {
    $this->repository->ensureStorage();
    $rows = $this->repository->forUser($uid, $unreadOnly);
    $items = [];
    foreach ($rows as $row) {
      $payload = json_decode((string) $row['payload'], TRUE, 512, JSON_THROW_ON_ERROR);
      $items[] = [
        'id' => (int) $row['id'],
        'project_nid' => (int) $row['project_nid'],
        'exception_id' => (int) $row['exception_id'],
        'attention' => (string) $row['attention'],
        'status' => (string) $row['status'],
        'created' => (int) $row['created'],
        'payload' => $payload,
        'decision_url' => '/brebo-office/finance/decision-inbox?exception_id=' . (int) $row['exception_id'],
      ];
    }
    return $items;
  }

  public function markReadForUser(int $outboxId, int $uid): void {
    $this->repository->ensureStorage();
    $affected = $this->repository->markReadForUser($outboxId, $uid, time()) ? 1 : 0;
    if ($affected === 0) throw new UnexpectedValueException('Notification is not available to this user or is already handled.');
  }

  public function unreadCount(int $uid): int {
    $this->repository->ensureStorage();
    return $this->repository->unreadCount($uid);
  }

  public function load(int $outboxId): ?array {
    $this->repository->ensureStorage();
    return $this->repository->load($outboxId);
  }

}
