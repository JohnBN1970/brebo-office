<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

use Drupal\brebo_office_core\Service\ProjectControllerActionService;
use Drupal\Core\Database\Connection;
use Drupal\node\NodeInterface;

/**
 * Persists and manages project controller actions.
 */
final class ControlActionManager {

  public function __construct(
    private readonly Connection $database,
    private readonly ProjectControllerActionService $controllerActions,
  ) {}

  /**
   * Synchronize calculated controller actions to persistent tasks.
   *
   * @return array<int, array<string, mixed>>
   */
  public function synchronize(NodeInterface $project): array {
    $analysis = $this->controllerActions->analyze($project);
    $activeCodes = [];
    $now = time();

    foreach ($analysis['actions'] as $action) {
      $code = (string) $action['code'];
      $activeCodes[] = $code;
      $existing = $this->database->select('brebo_control_action', 'a')
        ->fields('a')->condition('project_nid', (int) $project->id())
        ->condition('driver_code', $code)->execute()->fetchAssoc();

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
        // A resolved issue that becomes active again is reopened deliberately.
        if (in_array($existing['status'], ['completed', 'resolved', 'auto_resolved'], TRUE)) {
          $values['status'] = 'reopened';
          $values['completed_by'] = NULL;
          $values['completed_at'] = NULL;
          $values['resolution'] = NULL;
        }
        $this->database->update('brebo_control_action')->fields($values)
          ->condition('id', (int) $existing['id'])->execute();
      }
      else {
        $this->database->insert('brebo_control_action')->fields($values + [
          'project_nid' => (int) $project->id(),
          'driver_code' => $code,
          'status' => 'open',
          'escalation_level' => 0,
          'created' => $now,
        ])->execute();
      }
    }

    // Open actions disappear only when the underlying risk disappears.
    $query = $this->database->select('brebo_control_action', 'a')->fields('a')
      ->condition('project_nid', (int) $project->id())
      ->condition('status', ['open', 'reopened', 'in_progress', 'escalated'], 'IN');
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      if (!in_array($row['driver_code'], $activeCodes, TRUE)) {
        $this->database->update('brebo_control_action')->fields([
          'status' => 'auto_resolved',
          'resolution' => 'Onderliggend Early Warning-signaal is niet meer actief.',
          'completed_at' => $now,
          'changed' => $now,
        ])->condition('id', (int) $row['id'])->execute();
      }
    }

    return $this->loadProjectActions((int) $project->id());
  }

  /**
   * Complete an action only with evidence and resolution.
   */
  public function complete(int $actionId, int $userId, string $evidence, string $resolution): void {
    if (trim($evidence) === '' || trim($resolution) === '') {
      throw new \InvalidArgumentException('Bewijs en afrondingsverklaring zijn verplicht.');
    }
    $now = time();
    $this->database->update('brebo_control_action')->fields([
      'status' => 'completed',
      'evidence' => trim($evidence),
      'resolution' => trim($resolution),
      'completed_by' => $userId,
      'completed_at' => $now,
      'changed' => $now,
    ])->condition('id', $actionId)->execute();
  }

  /**
   * Escalate overdue actions and return those requiring attention.
   *
   * @return array<int, array<string, mixed>>
   */
  public function escalateOverdue(): array {
    $now = time();
    $rows = $this->database->select('brebo_control_action', 'a')->fields('a')
      ->condition('status', ['open', 'reopened', 'in_progress', 'escalated'], 'IN')
      ->condition('due_at', 0, '>')->condition('due_at', $now, '<')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
      $level = min(3, (int) $row['escalation_level'] + 1);
      $this->database->update('brebo_control_action')->fields([
        'status' => 'escalated',
        'escalation_level' => $level,
        'changed' => $now,
      ])->condition('id', (int) $row['id'])->execute();
      $row['status'] = 'escalated';
      $row['escalation_level'] = $level;
    }
    return $rows;
  }

  /** @return array<int, array<string, mixed>> */
  private function loadProjectActions(int $projectId): array {
    return $this->database->select('brebo_control_action', 'a')->fields('a')
      ->condition('project_nid', $projectId)
      ->orderBy('risk_points', 'DESC')->orderBy('due_at', 'ASC')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
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
