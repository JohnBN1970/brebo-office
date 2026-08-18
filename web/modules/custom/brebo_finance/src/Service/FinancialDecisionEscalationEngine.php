<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/** Tracks reminder and escalation state for pending financial decisions. */
final class FinancialDecisionEscalationEngine {

  public function __construct(
    private readonly Connection $database,
    private readonly FinancialDecisionInbox $inbox,
  ) {}

  /** @return list<array<string, mixed>> */
  public function evaluate(?int $projectNid = NULL): array {
    $this->ensureStorage();
    $now = time();
    $actions = [];

    foreach ($this->inbox->pending($projectNid) as $item) {
      $age = max(0, $now - (int) $item['created']);
      $deadline = $this->deadlineFor((string) $item['authorization']['level']);
      $dueAt = (int) $item['created'] + $deadline;
      $state = $this->loadState((int) $item['exception_id']);

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

      if ($changed) {
        $this->storeState((int) $item['exception_id'], (int) $item['project_nid'], $attention, $escalationLevel, $dueAt, $now);
        $this->audit($item, $attention, $escalationLevel, $dueAt, $now);
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
        'notification_channel' => 'pending_channel_adapter',
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

  private function ensureStorage(): void {
    $schema = $this->database->schema();
    if ($schema->tableExists('brebo_finance_decision_escalation')) return;
    $schema->createTable('brebo_finance_decision_escalation', [
      'description' => 'Reminder and escalation state for financial phase-gate decisions.',
      'fields' => [
        'exception_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'project_nid' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'attention' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
        'escalation_level' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 0],
        'due_at' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'changed' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
      ],
      'primary key' => ['exception_id'],
      'indexes' => [
        'project_attention' => ['project_nid', 'attention'],
        'due_at' => ['due_at'],
      ],
    ]);
  }

  private function loadState(int $exceptionId): ?array {
    $row = $this->database->select('brebo_finance_decision_escalation', 'e')
      ->fields('e')
      ->condition('exception_id', $exceptionId)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  private function storeState(int $exceptionId, int $projectNid, string $attention, int $level, int $dueAt, int $now): void {
    $this->database->merge('brebo_finance_decision_escalation')
      ->key(['exception_id' => $exceptionId])
      ->fields([
        'project_nid' => $projectNid,
        'attention' => $attention,
        'escalation_level' => $level,
        'due_at' => $dueAt,
        'changed' => $now,
      ])
      ->execute();
  }

  private function audit(array $item, string $attention, int $level, int $dueAt, int $now): void {
    $this->database->insert('brebo_finance_audit')->fields([
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
    ])->execute();
  }

}
