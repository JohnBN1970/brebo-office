<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_finance\Service\LabourProductivityManager;
use Drupal\brebo_inzet\Contract\PersonnelAssignmentRepositoryInterface;

/**
 * Submits and approves actual personnel hours.
 *
 * Operational approval belongs to the assignment. Finance synchronization is
 * a downstream consequence and may happen later when a labour budget exists.
 */
final class PersonnelActualHoursManager {

  public function __construct(
    private readonly PersonnelAssignmentComparison $comparison,
    private readonly LabourProductivityManager $labourProductivity,
    private readonly PersonnelLabourLineResolver $labourLineResolver,
    private readonly PersonnelAssignmentRepositoryInterface $assignmentRepository,
  ) {}

  public function submit(int $assignmentId, int $userId): int {
    $actual = $this->closedActual($assignmentId);
    $this->assignmentRepository->storeActualReview(
      $assignmentId,
      (float) $actual['clocked_hours'],
      'worked',
      $userId,
      gmdate('Y-m-d\\TH:i:s'),
    );
    return $this->synchronizeFinanceIfPossible($assignmentId, 'worked', $actual, $userId);
  }

  public function approve(int $assignmentId, int $userId): int {
    $assignment = $this->requireAssignment($assignmentId);
    if ($assignment['actual_status'] !== 'worked') {
      throw new \UnexpectedValueException('Alleen ingediende uren kunnen worden goedgekeurd.');
    }
    if ($assignment['actual_hours'] <= 0) {
      throw new \UnexpectedValueException('Ingediende uren bevatten geen werkelijke uren.');
    }

    $actual = $this->comparison->compare($assignmentId);
    $actual['clocked_hours'] = $assignment['actual_hours'];
    $this->assignmentRepository->storeActualReview(
      $assignmentId,
      $assignment['actual_hours'],
      'approved',
      $userId,
      gmdate('Y-m-d\\TH:i:s'),
    );
    return $this->synchronizeFinanceIfPossible($assignmentId, 'approved', $actual, $userId);
  }

  /** Backfills approved/submitted assignment hours into Finance when possible. */
  public function synchronizeFinanceIfPossible(
    int $assignmentId,
    ?string $status = NULL,
    ?array $actual = NULL,
    int $userId = 0,
  ): int {
    $assignment = $this->requireAssignment($assignmentId);
    $projectId = $assignment['project_id'];
    if ($projectId <= 0) {
      return 0;
    }

    $budgetLineId = $assignment['budget_line_id'];
    if ($budgetLineId <= 0) {
      try {
        $budgetLineId = (int) $this->labourLineResolver->resolve(
          $projectId,
          $assignment['hourly_cost'],
        )['id'];
        if ($budgetLineId > 0) {
          $this->assignmentRepository->setBudgetLine($assignmentId, $budgetLineId);
          $assignment['budget_line_id'] = $budgetLineId;
        }
      }
      catch (\Throwable) {
        return 0;
      }
    }
    if ($budgetLineId <= 0) {
      return 0;
    }

    $status ??= $assignment['actual_status'];
    if (!in_array($status, ['worked', 'approved'], TRUE)) {
      return 0;
    }

    $actual ??= $this->comparison->compare($assignmentId);
    $hours = max(0.0, $assignment['actual_hours'] ?: (float) ($actual['clocked_hours'] ?? 0));
    if ($hours <= 0) {
      return 0;
    }

    $planned = max(0.0, (float) ($actual['planned_hours'] ?? 0));
    $employeeCost = max(0.0, $assignment['hourly_cost']);
    $actualCost = number_format($hours * $employeeCost, 4, '.', '');
    $payload = [
      'assignment_nid' => $assignmentId,
      'project_nid' => $projectId,
      'budget_line_id' => $budgetLineId,
      'planned_hours' => $planned,
      'actual_hours' => $hours,
      'comparison_state' => (string) ($actual['state'] ?? ''),
      'approval_status' => $status,
      'employee_hourly_cost' => number_format($employeeCost, 2, '.', ''),
      'actual_cost_ex_vat' => $actualCost,
      'evidence' => $status === 'approved' ? 'sealed_operational_hours' : 'submitted_operational_hours',
    ];

    return $this->labourProductivity->synchronizeEntry(
      $projectId,
      $budgetLineId,
      'brebo_inzet_actual',
      'assignment:' . $assignmentId,
      $assignment['revision_id'],
      $assignmentId,
      NULL,
      NULL,
      NULL,
      NULL,
      '0.0000',
      number_format($hours, 4, '.', ''),
      NULL,
      $actualCost,
      $status,
      time(),
      $payload,
      $userId,
    );
  }

  /** @return array<string,mixed> */
  private function closedActual(int $assignmentId): array {
    $this->requireAssignment($assignmentId);
    $actual = $this->comparison->compare($assignmentId);
    if ((bool) $actual['open_session']) {
      throw new \UnexpectedValueException('Open klokregistraties kunnen nog niet worden ingediend.');
    }
    if ((float) $actual['clocked_hours'] <= 0) {
      throw new \UnexpectedValueException('Er zijn geen geklokte uren beschikbaar voor deze inzet.');
    }
    return $actual;
  }

  /** @return array<string,mixed> */
  private function requireAssignment(int $assignmentId): array {
    $assignment = $this->assignmentRepository->assignment($assignmentId);
    if ($assignment === NULL) {
      throw new \InvalidArgumentException('Only personnel assignments can be processed.');
    }
    return $assignment;
  }

}
