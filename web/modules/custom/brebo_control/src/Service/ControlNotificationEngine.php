<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

use Drupal\Core\Database\Connection;

/**
 * Creates notification events only when a control state deserves attention.
 */
final class ControlNotificationEngine {

  public function __construct(
    private readonly Connection $database,
    private readonly ControlEscalationMatrix $escalationMatrix,
  ) {}

  /**
   * Scan active actions and queue only new, meaningful notifications.
   *
   * @return array<int, array<string, mixed>>
   */
  public function scan(int $now): array {
    if (!$this->database->schema()->tableExists('brebo_control_action') || !$this->database->schema()->tableExists('brebo_control_notification')) {
      return [];
    }

    $rows = $this->database->select('brebo_control_action', 'a')->fields('a')
      ->condition('status', ['open', 'reopened', 'in_progress', 'escalated'], 'IN')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $queued = [];
    foreach ($rows as $action) {
      $events = $this->eventsForAction($action, $now);
      foreach ($events as $event) {
        foreach ($event['recipients'] as $recipient) {
          $record = $this->queue($action, (string) $event['type'], (string) $recipient, (string) $event['subject'], (string) $event['message'], (string) $event['state_key'], $now);
          if ($record !== NULL) {
            $queued[] = $record;
          }
        }
      }
    }
    return $queued;
  }

  /**
   * @param array<string, mixed> $action
   * @return array<int, array<string, mixed>>
   */
  private function eventsForAction(array $action, int $now): array {
    $events = [];
    $actionId = (int) $action['id'];
    $title = (string) $action['title'];
    $owner = (string) $action['owner_role'];
    $urgency = (string) $action['urgency'];
    $dueAt = (int) ($action['due_at'] ?? 0);
    $status = (string) $action['status'];

    if (in_array($status, ['open', 'reopened'], TRUE) && in_array($urgency, ['kritiek', 'vandaag'], TRUE)) {
      $events[] = [
        'type' => $status === 'reopened' ? 'reopened' : 'new_urgent',
        'recipients' => $this->ownerRecipients($owner),
        'subject' => $status === 'reopened' ? 'Controlleractie opnieuw actief' : 'Nieuwe urgente controlleractie',
        'message' => $title,
        'state_key' => $status . ':' . $urgency,
      ];
    }

    if ($dueAt > $now) {
      $hours = ($dueAt - $now) / 3600;
      if ($hours <= 4) {
        $events[] = [
          'type' => 'deadline_4h',
          'recipients' => $this->ownerRecipients($owner),
          'subject' => 'Deadline controlleractie nadert',
          'message' => $title . ' — nog maximaal 4 uur.',
          'state_key' => 'due:' . date('YmdHi', $dueAt) . ':4h',
        ];
      }
      elseif ($hours <= 24) {
        $events[] = [
          'type' => 'deadline_24h',
          'recipients' => $this->ownerRecipients($owner),
          'subject' => 'Controlleractie binnen 24 uur vervalt',
          'message' => $title,
          'state_key' => 'due:' . date('YmdHi', $dueAt) . ':24h',
        ];
      }
    }

    if ($dueAt > 0 && $dueAt < $now) {
      $decision = $this->escalationMatrix->determine($action, $now);
      $level = max((int) $action['escalation_level'], (int) $decision['level']);
      $events[] = [
        'type' => 'escalation_' . $level,
        'recipients' => $decision['recipients'],
        'subject' => 'Controlleractie geëscaleerd naar niveau ' . $level,
        'message' => $title . ' — ' . $decision['reason'],
        'state_key' => 'escalation:' . $level,
      ];
    }

    return $events;
  }

  /** @return string[] */
  private function ownerRecipients(string $owner): array {
    $roles = preg_split('/\s*\/\s*/', $owner) ?: [];
    return array_values(array_unique(array_filter(array_map('trim', $roles))));
  }

  /**
   * @param array<string, mixed> $action
   * @return array<string, mixed>|null
   */
  private function queue(array $action, string $type, string $recipient, string $subject, string $message, string $stateKey, int $now): ?array {
    $dedupKey = hash('sha256', implode('|', [(int) $action['id'], $type, $recipient, $stateKey]));
    $exists = $this->database->select('brebo_control_notification', 'n')
      ->condition('dedup_key', $dedupKey)->countQuery()->execute()->fetchField();
    if ((int) $exists > 0) {
      return NULL;
    }

    $id = $this->database->insert('brebo_control_notification')->fields([
      'action_id' => (int) $action['id'],
      'project_nid' => (int) $action['project_nid'],
      'event_type' => $type,
      'recipient_key' => $recipient,
      'dedup_key' => $dedupKey,
      'subject' => $subject,
      'message' => $message,
      'status' => 'pending',
      'created' => $now,
    ])->execute();

    return [
      'id' => (int) $id,
      'action_id' => (int) $action['id'],
      'project_nid' => (int) $action['project_nid'],
      'event_type' => $type,
      'recipient' => $recipient,
      'subject' => $subject,
      'message' => $message,
    ];
  }

}
