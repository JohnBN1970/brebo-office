<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\WorkingBudgetApprovalRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Controls multidisciplinary approval and locking of a working budget.
 */
final class WorkingBudgetApprovalManager {

  private const array REQUIRED_CHECKS = [
    'calculator_purchaser' => [
      'scope_complete',
      'quantities_checked',
      'prices_traceable',
      'labour_hours_and_rates_checked',
      'risk_and_tco_checked',
    ],
    'work_preparation' => [
      'technically_executable',
      'planning_checked',
      'logistics_checked',
      'building_allocation_complete',
    ],
    'project_management' => [
      'contract_scope_aligned',
      'budget_and_margin_checked',
      'project_risks_controlled',
      'responsibilities_assigned',
    ],
  ];

  public function __construct(private readonly WorkingBudgetApprovalRepositoryInterface $repository) {}

  /**
   * Records one review decision and locks after all disciplines approve.
   *
   * @param array<string, bool> $checklist
   *   Explicit review confirmations for the selected discipline.
   */
  public function decide(
    int $budgetId,
    string $discipline,
    string $decision,
    array $checklist,
    string $note,
    int $userId,
  ): bool {
    if (!isset(self::REQUIRED_CHECKS[$discipline])) {
      throw new InvalidArgumentException('Unknown working-budget approval discipline.');
    }
    if (!in_array($decision, ['approved', 'rejected'], TRUE)) {
      throw new InvalidArgumentException('Decision must be approved or rejected.');
    }
    if ($decision === 'rejected' && trim($note) === '') {
      throw new InvalidArgumentException('A rejection reason is required.');
    }
    if ($decision === 'approved') {
      foreach (self::REQUIRED_CHECKS[$discipline] as $requiredCheck) {
        if (($checklist[$requiredCheck] ?? FALSE) !== TRUE) {
          throw new InvalidArgumentException(sprintf('Required check "%s" is not confirmed.', $requiredCheck));
        }
      }
    }

    $budget = $this->repository->budget($budgetId);
    if ($budget === NULL || $budget['budget_type'] !== 'working') {
      throw new UnexpectedValueException('A working budget is required.');
    }
    if ($budget['status'] === 'locked') {
      throw new RuntimeException('The original working budget baseline is immutable.');
    }

    return $this->repository->transactional(function () use ($budgetId, $discipline, $decision, $checklist, $note, $userId, $budget): bool {
      $now = time();
      $this->repository->saveDecision($budgetId, $discipline, [
        'decision' => $decision,
        'checklist_payload' => json_encode($checklist, JSON_THROW_ON_ERROR),
        'note' => trim($note) !== '' ? trim($note) : NULL,
        'decided' => $now,
        'decided_by' => $userId,
      ]);

      $status = $decision === 'rejected' ? 'rejected' : 'in_review';
      $locked = FALSE;
      if ($decision === 'approved' && $this->allDisciplinesApproved($budgetId)) {
        $this->assertLabourBaselineComplete($budgetId);
        $status = 'locked';
        $locked = TRUE;
      }

      $fields = [
        'status' => $status,
        'changed' => $now,
        'changed_by' => $userId,
      ];
      if ($locked) {
        $fields += [
          'content_hash' => $this->baselineHash($budgetId),
          'approved' => $now,
          'approved_by' => $userId,
        ];
      }
      $this->repository->updateBudget($budgetId, $fields);

      $this->repository->insertAudit([
          'project_nid' => (int) $budget['project_nid'],
          'entity_type' => 'working_budget',
          'entity_id' => $budgetId,
          'action' => $locked ? 'baseline_locked' : 'review_decision',
          'after_hash' => $locked ? $fields['content_hash'] : NULL,
          'payload' => json_encode([
            'discipline' => $discipline,
            'decision' => $decision,
            'checklist' => $checklist,
            'resulting_status' => $status,
          ], JSON_THROW_ON_ERROR),
          'reason' => trim($note) !== '' ? trim($note) : 'Working-budget discipline review.',
          'created' => $now,
          'created_by' => $userId,
        ]);

      return $locked;
    });
  }

  private function allDisciplinesApproved(int $budgetId): bool {
    $decisions = $this->repository->decisions($budgetId);

    foreach (array_keys(self::REQUIRED_CHECKS) as $discipline) {
      if (($decisions[$discipline] ?? NULL) !== 'approved') {
        return FALSE;
      }
    }
    return TRUE;
  }


  /**
   * Prevents approval of labour money without an executable hours baseline.
   */
  private function assertLabourBaselineComplete(int $budgetId): void {
    if ($this->repository->hasInvalidLabourBaseline($budgetId)) {
      throw new UnexpectedValueException(
        'Every labour line requires approved budget hours and an hourly cost before baseline locking.',
      );
    }
  }

  /**
   * Hashes the operational baseline independently from its calculation source.
   */
  private function baselineHash(int $budgetId): string {
    $rows = $this->repository->baselineRows($budgetId);

    if ($rows === []) {
      throw new UnexpectedValueException('An empty working budget cannot become a baseline.');
    }

    return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
  }

}
