<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/** Converts an approved locked calculation version into a work budget. */
final class WorkBudgetConversionService {
  public function __construct(private readonly Connection $database) {}

  public function convert(int $calculationId, string $version, int $projectNid, int $uid): int {
    $source = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    if (!$source) {
      throw new \InvalidArgumentException('Calculatieversie bestaat niet.');
    }
    if (!in_array((string) $source['status'], ['approved', 'locked'], TRUE) || empty($source['locked_at']) || empty($source['content_hash'])) {
      throw new \LogicException('Alleen een goedgekeurde en vergrendelde calculatieversie met bronhash kan werkbegroting worden.');
    }

    $existing = $this->database->select('brebo_work_budget', 'b')
      ->fields('b', ['id'])
      ->condition('source_calculation_id', $calculationId)
      ->condition('source_calculation_version', $version)
      ->execute()->fetchField();
    if ($existing) {
      return (int) $existing;
    }

    $transaction = $this->database->startTransaction();
    $budgetId = (int) $this->database->insert('brebo_work_budget')->fields([
      'project_nid' => $projectNid,
      'source_calculation_id' => $calculationId,
      'source_calculation_version' => $version,
      'source_content_hash' => (string) $source['content_hash'],
      'status' => 'draft',
      'created' => time(),
      'created_by' => $uid,
    ])->execute();

    $query = $this->database->select('brebo_calculation_row_domain', 'd');
    $query->leftJoin('brebo_calc_line', 'l', 'l.id = d.calc_line_id');
    $query->fields('d');
    $query->addField('l', 'description', 'description');
    $query->addField('l', 'quantity', 'quantity');
    $query->addField('l', 'unit', 'unit');
    $query->condition('d.calculation_id', $calculationId)->condition('d.version', $version);
    foreach ($query->execute() as $row) {
      $quantity = (float) ($row->quantity ?? 0);
      $costs = [
        'labour' => (float) $row->labour_unit_cost,
        'material' => (float) $row->material_unit_cost,
        'equipment' => (float) $row->equipment_unit_cost,
        'subcontracting' => (float) $row->subcontracting_unit_cost,
        'other' => (float) $row->other_unit_cost,
      ];
      foreach ($costs as $costType => $unitCost) {
        if ($unitCost == 0.0) { continue; }
        $this->database->insert('brebo_work_budget_line')->fields([
          'work_budget_id' => $budgetId,
          'source_calc_line_id' => (int) $row->calc_line_id,
          'paragraph_key' => (string) $row->paragraph_key,
          'location_ref' => $row->location_ref,
          'cost_type' => $costType,
          'description' => (string) ($row->description ?? ''),
          'unit' => (string) ($row->unit ?? ''),
          'quantity' => $quantity,
          'budget_unit_cost' => $unitCost,
          'budget_amount' => $quantity * $unitCost,
        ])->execute();
      }
    }
    unset($transaction);
    return $budgetId;
  }
}
