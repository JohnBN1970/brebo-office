<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\brebo_inzet\Contract\PersonnelAssignmentRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

final class DrupalPersonnelAssignmentRepository implements PersonnelAssignmentRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function assignment(int $assignmentId): ?array {
    $assignment = $this->entityTypeManager->getStorage('node')->load($assignmentId);
    if (!$assignment instanceof NodeInterface || $assignment->bundle() !== 'brebo_personnel_assignment') {
      return NULL;
    }

    $user = $assignment->get('field_brebo_plan_user')->entity;
    $hourlyCost = $user instanceof UserInterface && $user->hasField('field_brebo_hourly_cost')
      ? max(0.0, (float) ($user->get('field_brebo_hourly_cost')->value ?? 0))
      : 0.0;

    return [
      'id' => (int) $assignment->id(),
      'revision_id' => (string) $assignment->getRevisionId(),
      'project_id' => (int) ($assignment->get('field_brebo_project_ref')->target_id ?? 0),
      'user_id' => (int) ($assignment->get('field_brebo_plan_user')->target_id ?? 0),
      'budget_line_id' => (int) ($assignment->get('field_brebo_budget_line_id')->value ?? 0),
      'planned_hours' => max(0.0, (float) ($assignment->get('field_brebo_planned_hours')->value ?? 0)),
      'actual_hours' => max(0.0, (float) ($assignment->get('field_brebo_actual_hours')->value ?? 0)),
      'actual_status' => (string) ($assignment->get('field_brebo_actual_status')->value ?? 'open'),
      'assignment_status' => (string) ($assignment->get('field_brebo_assignment_status')->value ?? 'planned'),
      'plan_date' => (string) ($assignment->get('field_brebo_plan_date')->value ?? ''),
      'start' => (string) ($assignment->get('field_brebo_assignment_start')->value ?? ''),
      'end' => (string) ($assignment->get('field_brebo_assignment_end')->value ?? ''),
      'hourly_cost' => $hourlyCost,
      'changed' => max(1, (int) $assignment->getChangedTime()),
    ];
  }

  public function setBudgetLine(int $assignmentId, int $budgetLineId): void {
    $assignment = $this->loadAssignment($assignmentId);
    $assignment->set('field_brebo_budget_line_id', $budgetLineId > 0 ? $budgetLineId : NULL);
    $assignment->save();
  }

  public function storeActualReview(int $assignmentId, float $hours, string $status, int $reviewedBy, string $reviewedAt): void {
    $assignment = $this->loadAssignment($assignmentId);
    $assignment->set('field_brebo_actual_hours', round(max(0.0, $hours), 2));
    $assignment->set('field_brebo_actual_status', $status);
    $assignment->set('field_brebo_actual_reviewed_by', $reviewedBy > 0 ? ['target_id' => $reviewedBy] : NULL);
    $assignment->set('field_brebo_actual_reviewed_at', $reviewedAt);
    $assignment->setNewRevision(TRUE);
    $assignment->setRevisionLogMessage('Werkelijke personeelsuren ' . ($status === 'approved' ? 'goedgekeurd' : 'ingediend') . '.');
    $assignment->save();
  }

  private function loadAssignment(int $assignmentId): NodeInterface {
    $assignment = $this->entityTypeManager->getStorage('node')->load($assignmentId);
    if (!$assignment instanceof NodeInterface || $assignment->bundle() !== 'brebo_personnel_assignment') {
      throw new \InvalidArgumentException('Only personnel assignments can be processed.');
    }
    return $assignment;
  }

}
