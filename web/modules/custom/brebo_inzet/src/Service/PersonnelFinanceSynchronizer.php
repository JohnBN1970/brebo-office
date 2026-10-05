<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_finance\Service\LabourProductivityManager;
use Drupal\brebo_inzet\Contract\PersonnelAssignmentRepositoryInterface;

/** Bridges canonical Inzet assignments into Finance planning evidence. */
final class PersonnelFinanceSynchronizer {

  public function __construct(
    private readonly LabourProductivityManager $labourProductivity,
    private readonly PersonnelAssignmentRepositoryInterface $assignmentRepository,
  ) {}

  public function synchronize(int $assignmentId): int {
    $assignment = $this->assignmentRepository->assignment($assignmentId);
    if ($assignment === NULL) {
      throw new \InvalidArgumentException('Only personnel assignments can be synchronized.');
    }

    $projectId = $assignment['project_id'];
    $budgetLineId = $assignment['budget_line_id'];
    $userId = $assignment['user_id'];
    $plannedHours = number_format($assignment['planned_hours'], 4, '.', '');
    $hourlyCost = max(0.0, $assignment['hourly_cost']);
    $plannedCost = number_format(((float) $plannedHours) * $hourlyCost, 4, '.', '');
    $status = in_array($assignment['assignment_status'], ['planned', 'confirmed', 'cancelled'], TRUE)
      ? $assignment['assignment_status']
      : 'planned';

    if ($projectId <= 0 || $plannedHours === '0.0000') {
      throw new \UnexpectedValueException('Finance synchronization requires a project and planned hours.');
    }
    if ($budgetLineId <= 0) {
      return 0;
    }

    $payload = [
      'assignment_nid' => $assignmentId,
      'project_nid' => $projectId,
      'budget_line_id' => $budgetLineId,
      'user_id' => $userId,
      'plan_date' => $assignment['plan_date'],
      'start' => $assignment['start'],
      'end' => $assignment['end'],
      'planned_hours' => $plannedHours,
      'status' => $status,
      'employee_hourly_cost' => number_format($hourlyCost, 2, '.', ''),
      'planned_cost_ex_vat' => $plannedCost,
    ];

    return $this->labourProductivity->synchronizeEntry(
      $projectId,
      $budgetLineId,
      'brebo_inzet',
      'assignment:' . $assignmentId,
      $assignment['revision_id'],
      $assignmentId,
      NULL,
      NULL,
      NULL,
      $userId > 0 ? 'user:' . $userId : NULL,
      $plannedHours,
      '0.0000',
      NULL,
      $plannedCost,
      $status,
      $assignment['changed'],
      $payload,
      0,
    );
  }

}
