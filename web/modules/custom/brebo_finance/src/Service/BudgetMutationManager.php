<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\BudgetMutationRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Manages explicit changes without altering the locked original baseline.
 */
final class BudgetMutationManager {

  public function __construct(
    private readonly BudgetMutationRepositoryInterface $repository,
    private readonly VatCalculator $vatCalculator,
  ) {}

  public function createDraft(
    int $budgetId,
    string $mutationNumber,
    string $mutationType,
    string $cause,
    string $description,
    string $consequence,
    string $controlMeasure,
    int $userId,
  ): int {
    foreach ([
      'mutationNumber' => $mutationNumber,
      'mutationType' => $mutationType,
      'cause' => $cause,
      'description' => $description,
      'consequence' => $consequence,
      'controlMeasure' => $controlMeasure,
    ] as $field => $value) {
      if (trim($value) === '') {
        throw new InvalidArgumentException("$field is required.");
      }
    }

    $budget = $this->loadLockedBudget($budgetId);
    $now = time();
    return $this->repository->insertMutation([
        'project_nid' => $budget['project_nid'],
        'budget_id' => $budgetId,
        'mutation_number' => trim($mutationNumber),
        'mutation_type' => trim($mutationType),
        'cause' => trim($cause),
        'description' => trim($description),
        'consequence' => trim($consequence),
        'control_measure' => trim($controlMeasure),
        'status' => 'draft',
        'amount_ex_vat' => '0.0000',
        'vat_amount' => '0.0000',
        'amount_inc_vat' => '0.0000',
        'requested' => $now,
        'requested_by' => $userId,
        'created' => $now,
        'created_by' => $userId,
        'changed' => $now,
        'changed_by' => $userId,
      ]);
  }

  public function addLine(
    int $mutationId,
    int $budgetLineId,
    string $description,
    string $adjustmentExVat,
    string $vatRate,
    bool $reverseCharge,
    int $userId,
  ): int {
    $mutation = $this->loadEditableMutation($mutationId);
    $this->assertBudgetLineBelongsToBudget($budgetLineId, (int) $mutation['budget_id']);
    if ($this->vatCalculator->compare($adjustmentExVat, '0') === 0) {
      throw new InvalidArgumentException('A budget adjustment may not be zero.');
    }

    $vat = $this->vatCalculator->calculate($adjustmentExVat, $vatRate, $reverseCharge);
    return $this->repository->transactional(function () use ($mutationId, $budgetLineId, $description, $adjustmentExVat, $vatRate, $reverseCharge, $userId, $vat): int {
      $now = time();
      $lineId = $this->repository->insertMutationLine([
          'mutation_id' => $mutationId,
          'budget_line_id' => $budgetLineId,
          'description' => trim($description) !== '' ? trim($description) : 'Budgetmutatie',
          'adjustment_ex_vat' => $vat->amountExVat,
          'vat_code' => $reverseCharge ? 'NL_REVERSE' : 'NL_' . str_replace('.0000', '', $vat->vatRate),
          'vat_rate' => $vat->vatRate,
          'vat_amount' => $vat->vatAmount,
          'adjustment_inc_vat' => $vat->amountIncVat,
          'created' => $now,
          'created_by' => $userId,
          'changed' => $now,
          'changed_by' => $userId,
        ]);

      $this->refreshTotals($mutationId, $now, $userId);
      return $lineId;
    });
  }

  /**
   * Approves or rejects a mutation; requester and approver must differ.
   */
  public function decide(
    int $mutationId,
    string $decision,
    string $note,
    int $userId,
  ): void {
    if (!in_array($decision, ['approved', 'rejected'], TRUE)) {
      throw new InvalidArgumentException('Decision must be approved or rejected.');
    }
    if (trim($note) === '') {
      throw new InvalidArgumentException('An approval or rejection note is required.');
    }

    $mutation = $this->loadEditableMutation($mutationId);
    if ((int) $mutation['requested_by'] === $userId) {
      throw new RuntimeException('A requester may not approve their own budget mutation.');
    }
    if ($decision === 'approved' && !$this->hasLines($mutationId)) {
      throw new RuntimeException('An empty budget mutation cannot be approved.');
    }

    $now = time();
    $this->repository->updateMutation($mutationId, [
        'status' => $decision,
        'approved' => $decision === 'approved' ? $now : NULL,
        'approved_by' => $decision === 'approved' ? $userId : NULL,
        'approval_note' => trim($note),
        'changed' => $now,
        'changed_by' => $userId,
      ]);

    $this->repository->insertAudit([
        'project_nid' => $mutation['project_nid'],
        'entity_type' => 'budget_mutation',
        'entity_id' => $mutationId,
        'action' => $decision,
        'payload' => json_encode([
          'mutation_number' => $mutation['mutation_number'],
          'amount_ex_vat' => $mutation['amount_ex_vat'],
          'decision' => $decision,
        ], JSON_THROW_ON_ERROR),
        'reason' => trim($note),
        'created' => $now,
        'created_by' => $userId,
      ]);
  }

  private function loadLockedBudget(int $budgetId): array {
    $record = $this->repository->lockedWorkingBudget($budgetId);
    if ($record === NULL) {
      throw new UnexpectedValueException('A locked working budget is required.');
    }
    return $record;
  }

  private function loadEditableMutation(int $mutationId): array {
    $record = $this->repository->editableMutation($mutationId);
    if ($record === NULL || !in_array($record['status'], ['draft', 'in_review'], TRUE)) {
      throw new UnexpectedValueException('An editable budget mutation is required.');
    }
    return $record;
  }

  private function assertBudgetLineBelongsToBudget(int $lineId, int $budgetId): void {
    if (!$this->repository->budgetLineBelongsToBudget($lineId, $budgetId)) {
      throw new UnexpectedValueException('Mutation line does not belong to the locked baseline.');
    }
  }

  private function hasLines(int $mutationId): bool {
    return $this->repository->mutationHasLines($mutationId);
  }

  private function refreshTotals(int $mutationId, int $now, int $userId): void {
    $totals = $this->repository->mutationTotals($mutationId);
    $this->repository->updateMutation($mutationId, [
      'amount_ex_vat' => $totals['amount_ex_vat'],
      'vat_amount' => $totals['vat_amount'],
      'amount_inc_vat' => $totals['amount_inc_vat'],
      'changed' => $now,
      'changed_by' => $userId,
    ]);
  }

}
