<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\brebo_inzet\Contract\PersonnelAssignmentComparisonRepositoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal adapter for personnel assignment comparison reads. */
final class DrupalPersonnelAssignmentComparisonRepository implements PersonnelAssignmentComparisonRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  public function assignment(int $assignmentId): ?array {
    $assignment = $this->entityTypeManager->getStorage('node')->load($assignmentId);
    if (!$assignment instanceof NodeInterface || $assignment->bundle() !== 'brebo_personnel_assignment') {
      return NULL;
    }

    $explicit = (float) ($assignment->get('field_brebo_planned_hours')->value ?? 0);
    $start = (string) ($assignment->get('field_brebo_assignment_start')->value ?? '');
    $end = (string) ($assignment->get('field_brebo_assignment_end')->value ?? '');

    return [
      'id' => (int) $assignment->id(),
      'date' => (string) ($assignment->get('field_brebo_plan_date')->value ?? ''),
      'project_id' => (int) ($assignment->get('field_brebo_project_ref')->target_id ?? 0),
      'user_id' => (int) ($assignment->get('field_brebo_plan_user')->target_id ?? 0),
      'planned_hours' => $explicit > 0 ? round($explicit, 2) : $this->durationHours($start, $end),
      'start' => $start,
      'end' => $end,
    ];
  }

  public function clockSessions(int $projectId, int $userId, string $beforeUtc): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_clock_registration')
      ->condition('field_brebo_project_ref', $projectId)
      ->condition('field_brebo_clock_user', $userId)
      ->condition('field_brebo_clock_in', $beforeUtc, '<')
      ->execute();

    $sessions = [];
    foreach ($storage->loadMultiple($ids) as $clock) {
      if (!$clock instanceof NodeInterface || !$clock->access('view')) {
        continue;
      }
      $in = (string) ($clock->get('field_brebo_clock_in')->value ?? '');
      if ($in === '') {
        continue;
      }
      $out = (string) ($clock->get('field_brebo_clock_out')->value ?? '');
      $sessions[] = [
        'clock_in' => $in,
        'clock_out' => $out !== '' ? $out : NULL,
      ];
    }
    return $sessions;
  }

  public function timezoneName(): string {
    return (string) ($this->configFactory->get('system.date')->get('timezone.default') ?: date_default_timezone_get());
  }

  private function durationHours(string $start, string $end): float {
    if (preg_match('/^(\d{2}):(\d{2})$/', $start, $startParts) !== 1 || preg_match('/^(\d{2}):(\d{2})$/', $end, $endParts) !== 1) {
      return 0.0;
    }
    $startMinutes = ((int) $startParts[1] * 60) + (int) $startParts[2];
    $endMinutes = ((int) $endParts[1] * 60) + (int) $endParts[2];
    return $endMinutes > $startMinutes ? round(($endMinutes - $startMinutes) / 60, 2) : 0.0;
  }

}
