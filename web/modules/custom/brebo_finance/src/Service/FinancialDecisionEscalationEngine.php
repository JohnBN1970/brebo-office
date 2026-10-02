<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\FinancialDecisionEscalationRepositoryInterface;

/** Tracks reminder and escalation state for pending financial decisions. */
final class FinancialDecisionEscalationEngine {

  public function __construct(
    private readonly FinancialDecisionEscalationRepositoryInterface $repository,
    private readonly FinancialDecisionInbox $inbox,
    private readonly FinancialNotificationOutbox $notificationOutbox,
  ) {}

  /** @return list<array<string, mixed>> */
  public function evaluate(?int $projectNid = NULL): array {
    $now = time();
    $actions = [];

    foreach ($this->inbox->pending($projectNid) as $item) {
      $age = max(0, $now - (int) $item['created']);
      $deadline = $this->deadlineFor((string) $item['authorization']['level']);
      $dueAt = (int) $item['created'] + $deadline;
      $state = $this->repository->state((int) $item['exception_id']);

      $attention = 'pending';
      $escalationLevel = 0;
      if ($item['assignment']['escalation_required']) {
        $attention = 'assignment_escalation';
        $escalationLevel = 2;
      }
      elseif ($now >= $dueAt) {
        $attention = 'overdue';
        $escalationLevel = 2;
      }
      elseif ($age >= intdiv($deadline, 2)) {
        $attention = 'reminder_due';
        $escalationLevel = 1;
      }

      $changed = $state === NULL
        || (string) $state['attention'] !== $attention
        || (int) $state['escalation_level'] !== $escalationLevel;

      $outboxId = NULL;
      if ($changed) {
        $this->repository->store((int) $item['exception_id'], (int) $item['project_nid'], $attention, $escalationLevel, $dueAt, $now);
        $this->audit($item, $attention, $escalationLevel, $dueAt, $now);
        if (in_array($attention, ['reminder_due', 'overdue', 'assignment_escalation'], TRUE)) {
          $outboxId = $this->notificationOutbox->enqueue($item, $attention, $escalationLevel, $dueAt);
        }
      }

      $actions[] = [
        'exception_id' => (int) $item['exception_id'],
        'project_nid' => (int) $item['project_nid'],
        'gate' => (string) $item['gate'],
        'attention' => $attention,
        'escalation_level' => $escalationLevel,
        'due_at' => $dueAt,
        'primary_candidate' => $item['assignment']['primary_candidate'] ?? NULL,
        'candidate_count' => (int) ($item['assignment']['candidate_count'] ?? 0),
        'notification_required' => in_array($attention, ['reminder_due', 'overdue', 'assignment_escalation'], TRUE),
        'notification_channel' => 'outbox',
        'outbox_id' => $outboxId,
      ];
    }

    return $actions;
  }

  private function deadlineFor(string $level): int {
    return match ($level) {
      'executive', 'executive_unresolved_exposure' => 4 * 3600,
      'finance_controller' => 8 * 3600,
      default => 24 * 3600,
    };
  }

  private function audit(array $item, string $attention, int $level, int $dueAt, int $now): void {
    $this->repository->audit([
      'project_nid' => (int) $item['project_nid'],
      'entity_type' => 'financial_decision_escalation',
      'entity_id' => (int) $item['exception_id'],
      'action' => 'decision_' . $attention,
      'payload' => json_encode([
        'gate' => $item['gate'],
        'escalation_level' => $level,
        'due_at' => $dueAt,
        'assignment' => $item['assignment'],
        'exposure' => $item['exposure'],
      ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
      'reason' => 'Automated financial decision reminder/escalation evaluation; no decision is made automatically.',
      'created' => $now,
      'created_by' => 0,
    ]);
  }

}
