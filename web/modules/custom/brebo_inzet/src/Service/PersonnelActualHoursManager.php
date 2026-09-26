<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_finance\Service\LabourProductivityManager;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\NodeInterface;

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
  ) {}

  public function submit(NodeInterface $assignment, int $userId): int {
    $actual = $this->closedActual($assignment);
    $this->storeOperationalReview($assignment, $actual['clocked_hours'], 'worked', $userId);
    return $this->synchronizeFinanceIfPossible($assignment, 'worked', $actual, $userId);
  }

  public function approve(NodeInterface $assignment, int $userId): int {
    $this->assertAssignment($assignment);
    $status = (string) ($assignment->get('field_brebo_actual_status')->value ?? 'open');
    if ($status !== 'worked') {
      throw new \UnexpectedValueException('Alleen ingediende uren kunnen worden goedgekeurd.');
    }

    $hours = max(0.0, (float) ($assignment->get('field_brebo_actual_hours')->value ?? 0));
    if ($hours <= 0) {
      throw new \UnexpectedValueException('Ingediende uren bevatten geen werkelijke uren.');
    }

    $actual = $this->comparison->compare($assignment);
    $actual['clocked_hours'] = $hours;
    $this->storeOperationalReview($assignment, $hours, 'approved', $userId);
    return $this->synchronizeFinanceIfPossible($assignment, 'approved', $actual, $userId);
  }

  /**
   * Backfills an approved/ submitted assignment into Finance when possible.
   */
  public function synchronizeFinanceIfPossible(NodeInterface $assignment, ?string $status = NULL, ?array $actual = NULL, int $userId = 0): int {
    $this->assertAssignment($assignment);
    $projectId = (int) ($assignment->get('field_brebo_project_ref')->target_id ?? 0);
    if ($projectId <= 0) {
      return 0;
    }

    $budgetLineId = (int) ($assignment->get('field_brebo_budget_line_id')->value ?? 0);
    if ($budgetLineId <= 0) {
      $account = $assignment->get('field_brebo_plan_user')->entity;
      if ($account instanceof \Drupal\user\UserInterface) {
        try {
          $budgetLineId = (int) $this->labourLineResolver->resolve($projectId, $account)['id'];
          if ($budgetLineId > 0) {
            $assignment->set('field_brebo_budget_line_id', $budgetLineId);
            $assignment->save();
          }
        }
        catch (\Throwable) {
          return 0;
        }
      }
    }
    if ($budgetLineId <= 0) {
      return 0;
    }

    $status ??= (string) ($assignment->get('field_brebo_actual_status')->value ?? 'open');
    if (!in_array($status, ['worked', 'approved'], TRUE)) {
      return 0;
    }

    $actual ??= $this->comparison->compare($assignment);
    $hours = max(0.0, (float) ($assignment->get('field_brebo_actual_hours')->value ?? $actual['clocked_hours'] ?? 0));
    if ($hours <= 0) {
      return 0;
    }

    $planned = max(0.0, (float) ($actual['planned_hours'] ?? 0));
    $employeeCost = $this->employeeHourlyCost($assignment);
    $actualCost = number_format($hours * $employeeCost, 4, '.', '');
    $payload = [
      'assignment_nid' => (int) $assignment->id(),
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
      $projectId, $budgetLineId, 'brebo_inzet_actual', 'assignment:' . $assignment->id(),
      (string) $assignment->getRevisionId(), (int) $assignment->id(), NULL, NULL, NULL,
      NULL, '0.0000', number_format($hours, 4, '.', ''),
      NULL, $actualCost, $status, time(), $payload, $userId,
    );
  }

  /** @return array<string,mixed> */
  private function closedActual(NodeInterface $assignment): array {
    $this->assertAssignment($assignment);
    $actual = $this->comparison->compare($assignment);
    if ((bool) $actual['open_session']) {
      throw new \UnexpectedValueException('Open klokregistraties kunnen nog niet worden ingediend.');
    }
    if ((float) $actual['clocked_hours'] <= 0) {
      throw new \UnexpectedValueException('Er zijn geen geklokte uren beschikbaar voor deze inzet.');
    }
    return $actual;
  }

  private function storeOperationalReview(NodeInterface $assignment, float $hours, string $status, int $userId): void {
    $assignment->set('field_brebo_actual_hours', round(max(0.0, $hours), 2));
    $assignment->set('field_brebo_actual_status', $status);
    $assignment->set('field_brebo_actual_reviewed_by', $userId > 0 ? ['target_id' => $userId] : NULL);
    $assignment->set('field_brebo_actual_reviewed_at', gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT));
    $assignment->setNewRevision(TRUE);
    $assignment->setRevisionLogMessage('Werkelijke personeelsuren ' . ($status === 'approved' ? 'goedgekeurd' : 'ingediend') . '.');
    $assignment->save();
  }

  private function employeeHourlyCost(NodeInterface $assignment): float {
    $account = $assignment->get('field_brebo_plan_user')->entity;
    if (!$account instanceof \Drupal\user\UserInterface || !$account->hasField('field_brebo_hourly_cost')) {
      return 0.0;
    }
    return max(0.0, (float) ($account->get('field_brebo_hourly_cost')->value ?? 0));
  }

  private function assertAssignment(NodeInterface $assignment): void {
    if ($assignment->bundle() !== 'brebo_personnel_assignment') {
      throw new \InvalidArgumentException('Only personnel assignments can be processed.');
    }
  }

}
