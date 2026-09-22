<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_finance\Service\LabourProductivityManager;
use Drupal\node\NodeInterface;

/** Bridges canonical Inzet assignments into Finance planning evidence. */
final class PersonnelFinanceSynchronizer {
  public function __construct(private readonly LabourProductivityManager $labourProductivity) {}

  public function synchronize(NodeInterface $assignment): int {
    if ($assignment->bundle() !== 'brebo_personnel_assignment') {
      throw new \InvalidArgumentException('Only personnel assignments can be synchronized.');
    }
    $projectId = (int) ($assignment->get('field_brebo_project_ref')->target_id ?? 0);
    $budgetLineId = (int) ($assignment->get('field_brebo_budget_line_id')->value ?? 0);
    $userId = (int) ($assignment->get('field_brebo_plan_user')->target_id ?? 0);
    $plannedHours = number_format(max(0.0, (float) ($assignment->get('field_brebo_planned_hours')->value ?? 0)), 4, '.', '');
    $assignmentStatus = (string) ($assignment->get('field_brebo_assignment_status')->value ?? 'planned');
    $status = in_array($assignmentStatus, ['planned', 'confirmed', 'cancelled'], TRUE) ? $assignmentStatus : 'planned';
    if ($projectId <= 0 || $budgetLineId <= 0 || $plannedHours === '0.0000') {
      throw new \UnexpectedValueException('Finance synchronization requires project, labour budget line and planned hours.');
    }
    $payload = [
      'assignment_nid' => (int) $assignment->id(), 'project_nid' => $projectId,
      'budget_line_id' => $budgetLineId, 'user_id' => $userId,
      'plan_date' => (string) ($assignment->get('field_brebo_plan_date')->value ?? ''),
      'start' => (string) ($assignment->get('field_brebo_assignment_start')->value ?? ''),
      'end' => (string) ($assignment->get('field_brebo_assignment_end')->value ?? ''),
      'planned_hours' => $plannedHours, 'status' => $status,
    ];
    return $this->labourProductivity->synchronizeEntry(
      $projectId, $budgetLineId, 'brebo_inzet', 'assignment:' . $assignment->id(),
      (string) $assignment->getRevisionId(), (int) $assignment->id(), NULL, NULL, NULL,
      $userId > 0 ? 'user:' . $userId : NULL, $plannedHours, '0.0000', NULL, '0.0000',
      $status, max(1, (int) $assignment->getChangedTime()), $payload, 0,
    );
  }
}
