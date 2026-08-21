<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/** Approves a work budget and freezes its original execution baseline. */
final class WorkBudgetApprovalService {
  public function __construct(private readonly Connection $database) {}

  public function approve(int $workBudgetId, int $uid): string {
    $budget = $this->database->select('brebo_work_budget', 'b')
      ->fields('b')
      ->condition('id', $workBudgetId)
      ->execute()->fetchAssoc();
    if (!$budget) {
      throw new \InvalidArgumentException('Werkbegroting bestaat niet.');
    }
    if ((string) $budget['status'] === 'approved') {
      return (string) ($budget['approved_content_hash'] ?? '');
    }
    if ((string) $budget['status'] !== 'draft') {
      throw new \LogicException('Alleen een conceptwerkbegroting kan worden goedgekeurd.');
    }

    $lines = $this->database->select('brebo_work_budget_line', 'l')
      ->fields('l')
      ->condition('work_budget_id', $workBudgetId)
      ->orderBy('source_calc_line_id')
      ->orderBy('cost_type')
      ->execute()->fetchAllAssoc('id');
    if ($lines === []) {
      throw new \LogicException('Een lege werkbegroting kan niet worden goedgekeurd.');
    }

    $payload = [];
    foreach ($lines as $line) {
      $payload[] = [
        'source_calc_line_id' => (int) $line->source_calc_line_id,
        'paragraph_key' => (string) $line->paragraph_key,
        'location_ref' => $line->location_ref,
        'cost_type' => (string) $line->cost_type,
        'description' => (string) $line->description,
        'unit' => $line->unit,
        'quantity' => (string) $line->quantity,
        'budget_unit_cost' => (string) $line->budget_unit_cost,
        'budget_amount' => (string) $line->budget_amount,
      ];
    }
    $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

    $this->database->update('brebo_work_budget')->fields([
      'status' => 'approved',
      'approved_content_hash' => $hash,
      'approved' => time(),
      'approved_by' => $uid,
    ])->condition('id', $workBudgetId)->condition('status', 'draft')->execute();

    return $hash;
  }

  public function assertApprovedBaselineIntact(int $workBudgetId): void {
    $budget = $this->database->select('brebo_work_budget', 'b')->fields('b')->condition('id', $workBudgetId)->execute()->fetchAssoc();
    if (!$budget || (string) $budget['status'] !== 'approved' || empty($budget['approved_content_hash'])) {
      throw new \LogicException('Werkbegroting heeft geen goedgekeurd uitgangsbudget.');
    }
    $current = $this->calculateCurrentHash($workBudgetId);
    if (!hash_equals((string) $budget['approved_content_hash'], $current)) {
      throw new \RuntimeException('Goedgekeurde werkbegroting is gewijzigd; uitgangsbudget-integriteit geschonden.');
    }
  }

  private function calculateCurrentHash(int $workBudgetId): string {
    $lines = $this->database->select('brebo_work_budget_line', 'l')->fields('l')
      ->condition('work_budget_id', $workBudgetId)->orderBy('source_calc_line_id')->orderBy('cost_type')->execute();
    $payload = [];
    foreach ($lines as $line) {
      $payload[] = [
        'source_calc_line_id' => (int) $line->source_calc_line_id,
        'paragraph_key' => (string) $line->paragraph_key,
        'location_ref' => $line->location_ref,
        'cost_type' => (string) $line->cost_type,
        'description' => (string) $line->description,
        'unit' => $line->unit,
        'quantity' => (string) $line->quantity,
        'budget_unit_cost' => (string) $line->budget_unit_cost,
        'budget_amount' => (string) $line->budget_amount,
      ];
    }
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
  }
}
