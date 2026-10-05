<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

use Drupal\brebo_control\Contract\ControlActionRepositoryInterface;

/**
 * Persists and manages project controller actions.
 */
final class ControlActionManager {

  public function __construct(
    private readonly ControlActionRepositoryInterface $actions,
    private readonly ControlEscalationMatrix $escalationMatrix,
  ) {}

  /**
   * Synchronize calculated controller actions to persistent tasks.
   *
   * @return array<int, array<string, mixed>>
   */
  public function synchronize(int $projectId, ?array $analysis): array {
    if ($analysis === NULL) {
      return $this->loadProjectActions($projectId);
    }
    $activeCodes = [];
    $now = time();

    foreach ($analysis['actions'] as $action) {
      $code = (string) $action['code'];
      $activeCodes[] = $code;
      $existing = $this->actions->byProjectDriver($projectId, $code);

      $values = [
        'title' => (string) $action['title'],
        'instruction' => (string) $action['instruction'],
        'done_when' => (string) $action['done_when'],
        'owner_role' => (string) $action['owner'],
        'urgency' => (string) $action['urgency'],
        'risk_points' => (int) $action['points'],
        'source_value' => round((float) $action['value'], 2),
        'due_at' => $this->dueAt((string) $action['urgency'], $now),
        'changed' => $now,
      ];

      if ($existing) {
        if (in_array($existing['status'], ['completed', 'resolved', 'auto_resolved'], TRUE)) {
          $values['status'] = 'reopened';
          $values['completed_by'] = NULL;
          $values['completed_at'] = NULL;
          $values['resolution'] = NULL;
        }
        $this->actions->update((int) $existing['id'], $values);
      }
      else {
        $this->actions->create($projectId, $code, $values + [
          'status' => 'open',
          'escalation_level' => 0,
          'created' => $now,
        ]);
      }
    }

    foreach ($this->actions->projectActions($projectId) as $row) {
      if (!in_array((string) $row['status'], ['open', 'reopened', 'in_progress', 'escalated'], TRUE)) {
        continue;
      }
      if (!in_array($row['driver_code'], $activeCodes, TRUE)) {
        $this->actions->update((int) $row['id'], [
          'status' => 'auto_resolved',
          'resolution' => 'Onderliggend Early Warning-signaal is niet meer actief.',
          'completed_at' => $now,
          'changed' => $now,
        ]);
      }
    }

    return $this->loadProjectActions($projectId);
  }

  public function complete(int $actionId, int $userId, string $evidence, string $resolution): void {
    if (trim($evidence) === '' || trim($resolution) === '') {
      throw new \InvalidArgumentException('Bewijs en afrondingsverklaring zijn verplicht.');
    }
    $now = time();
    $this->actions->update($actionId, [
      'status' => 'completed',
      'evidence' => trim($evidence),
      'resolution' => trim($resolution),
      'completed_by' => $userId,
      'completed_at' => $now,
      'changed' => $now,
    ]);
  }

  /**
   * Escalate overdue actions according to financial impact, age and risk.
   *
   * @return array<int, array<string, mixed>>
   */
  public function escalateOverdue(): array {
    $now = time();
    $rows = $this->actions->overdueRows($now);

    foreach ($rows as &$row) {
      $decision = $this->escalationMatrix->determine($row, $now);
      $currentLevel = (int) $row['escalation_level'];
      $level = max($currentLevel, (int) $decision['level']);
      $this->actions->update((int) $row['id'], [
        'status' => 'escalated',
        'escalation_level' => $level,
        'changed' => $now,
      ]);
      $row['status'] = 'escalated';
      $row['escalation_level'] = $level;
      $row['escalation_recipients'] = $decision['recipients'];
      $row['escalation_reason'] = $decision['reason'];
      $row['overdue_hours'] = $decision['age_hours'];
    }
    unset($row);
    return $rows;
  }

  /** @return array<int, array<string, mixed>> */
  private function loadProjectActions(int $projectId): array {
    return $this->actions->projectActions($projectId);
  }

  private function dueAt(string $urgency, int $now): int {
    return match ($urgency) {
      'kritiek' => $now + 4 * 3600,
      'vandaag' => strtotime('today 17:00', $now) ?: $now + 8 * 3600,
      'deze_week' => $now + 5 * 86400,
      default => $now + 14 * 86400,
    };
  }

}
