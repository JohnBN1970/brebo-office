<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_finance\Service\LabourProductivityManager;
use Drupal\node\NodeInterface;

/** Submits and approves clocked assignment hours into Finance. */
final class PersonnelActualHoursManager {
  public function __construct(
    private readonly PersonnelAssignmentComparison $comparison,
    private readonly LabourProductivityManager $labourProductivity,
  ) {}

  public function submit(NodeInterface $assignment, int $userId): int {
    return $this->synchronize($assignment, 'worked', $userId);
  }

  public function approve(NodeInterface $assignment, int $userId): int {
    if ($assignment->bundle() !== 'brebo_personnel_assignment') {
      throw new \InvalidArgumentException('Only personnel assignments can be processed.');
    }
    $projectId = (int) ($assignment->get('field_brebo_project_ref')->target_id ?? 0);
    $budgetLineId = (int) ($assignment->get('field_brebo_budget_line_id')->value ?? 0);
    if ($projectId <= 0 || $budgetLineId <= 0) {
      throw new \UnexpectedValueException('Assignment must reference a project and labour budget line.');
    }

    $submitted = $this->labourProductivity->inzetActualStatus($projectId, (int) $assignment->id());
    if ($submitted === NULL || $submitted['status'] !== 'worked') {
      throw new \UnexpectedValueException('Clocked hours must be submitted before they can be approved.');
    }
    $submittedHours = max(0.0, (float) $submitted['actual_hours']);
    $employeeCost = $this->employeeHourlyCost($assignment);
    $actualCost = number_format($submittedHours * $employeeCost, 4, '.', '');
    if ($submittedHours <= 0) {
      throw new \UnexpectedValueException('Submitted evidence contains no actual hours.');
    }

    $payload = [
      'assignment_nid' => (int) $assignment->id(),
      'project_nid' => $projectId,
      'budget_line_id' => $budgetLineId,
      'actual_hours' => $submittedHours,
      'submitted_at' => (int) $submitted['changed'],
      'approval_status' => 'approved',
      'employee_hourly_cost' => number_format($employeeCost, 2, '.', ''),
      'actual_cost_ex_vat' => $actualCost,
      'evidence' => 'sealed_submitted_hours',
    ];
    return $this->labourProductivity->synchronizeEntry(
      $projectId, $budgetLineId, 'brebo_inzet_actual', 'assignment:' . $assignment->id(),
      (string) $assignment->getRevisionId(), (int) $assignment->id(), NULL, NULL, NULL,
      NULL, '0.0000', number_format($submittedHours, 4, '.', ''),
      NULL, $actualCost, 'approved', time(), $payload, $userId,
    );
  }

  private function employeeHourlyCost(NodeInterface $assignment): float {
    $account = $assignment->get('field_brebo_plan_user')->entity;
    if (!$account instanceof \Drupal\user\UserInterface || !$account->hasField('field_brebo_hourly_cost')) {
      return 0.0;
    }
    return max(0.0, (float) ($account->get('field_brebo_hourly_cost')->value ?? 0));
  }

  private function synchronize(NodeInterface $assignment, string $status, int $userId): int {
    if ($assignment->bundle() !== 'brebo_personnel_assignment') {
      throw new \InvalidArgumentException('Only personnel assignments can be processed.');
    }
    $projectId = (int) ($assignment->get('field_brebo_project_ref')->target_id ?? 0);
    $budgetLineId = (int) ($assignment->get('field_brebo_budget_line_id')->value ?? 0);
    if ($projectId <= 0 || $budgetLineId <= 0) {
      throw new \UnexpectedValueException('Assignment must reference a project and labour budget line.');
    }
    $actual = $this->comparison->compare($assignment);
    if ((bool) $actual['open_session']) {
      throw new \UnexpectedValueException('Open clock sessions cannot be submitted or approved.');
    }
    $clocked = max(0.0, (float) $actual['clocked_hours']);
    if ($clocked <= 0) {
      throw new \UnexpectedValueException('No clocked hours are available for this assignment.');
    }
    $planned = max(0.0, (float) $actual['planned_hours']);
    $employeeCost = $this->employeeHourlyCost($assignment);
    $actualCost = number_format($clocked * $employeeCost, 4, '.', '');
    $payload = [
      'assignment_nid' => (int) $assignment->id(),
      'project_nid' => $projectId,
      'budget_line_id' => $budgetLineId,
      'planned_hours' => $planned,
      'actual_hours' => $clocked,
      'comparison_state' => (string) $actual['state'],
      'approval_status' => $status,
      'employee_hourly_cost' => number_format($employeeCost, 2, '.', ''),
      'actual_cost_ex_vat' => $actualCost,
    ];
    return $this->labourProductivity->synchronizeEntry(
      $projectId, $budgetLineId, 'brebo_inzet_actual', 'assignment:' . $assignment->id(),
      (string) $assignment->getRevisionId(), (int) $assignment->id(), NULL, NULL, NULL,
      NULL, '0.0000', number_format($clocked, 4, '.', ''),
      NULL, $actualCost, $status, time(), $payload, $userId,
    );
  }
}
