<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/** Manages additive calculation lines for concrete object deviations. */
final class ObjectExceptionLineManager {

  public function __construct(private readonly Connection $database) {}

  /** @param array<string,mixed> $values */
  public function addLine(int $applicationObjectId, array $values, AccountInterface $account): int {
    $context = $this->loadEditableContext($applicationObjectId, $account);
    $description = trim((string) ($values['description'] ?? ''));
    if ($description === '') {
      throw new \InvalidArgumentException('Description is required.');
    }
    $quantity = (float) ($values['quantity'] ?? 0);
    if ($quantity < 0) {
      throw new \InvalidArgumentException('Quantity cannot be negative.');
    }
    $costFields = [
      'labour_unit_cost',
      'material_unit_cost',
      'equipment_unit_cost',
      'subcontracting_unit_cost',
      'other_unit_cost',
    ];
    foreach ($costFields as $field) {
      if ((float) ($values[$field] ?? 0) < 0) {
        throw new \InvalidArgumentException('Unit costs cannot be negative.');
      }
    }
    $now = time();
    $id = (int) $this->database->insert('brebo_calculation_subcalculation_object_exception_line')->fields([
      'application_object_id' => $applicationObjectId,
      'code' => trim((string) ($values['code'] ?? '')) ?: NULL,
      'description' => $description,
      'quantity' => $quantity,
      'unit' => trim((string) ($values['unit'] ?? '')) ?: NULL,
      'labour_unit_cost' => (float) ($values['labour_unit_cost'] ?? 0),
      'material_unit_cost' => (float) ($values['material_unit_cost'] ?? 0),
      'equipment_unit_cost' => (float) ($values['equipment_unit_cost'] ?? 0),
      'subcontracting_unit_cost' => (float) ($values['subcontracting_unit_cost'] ?? 0),
      'other_unit_cost' => (float) ($values['other_unit_cost'] ?? 0),
      'price_source_ref' => trim((string) ($values['price_source_ref'] ?? '')) ?: NULL,
      'note' => trim((string) ($values['note'] ?? '')) ?: NULL,
      'sort_order' => $this->nextSortOrder($applicationObjectId),
      'created' => $now,
      'created_by' => (int) $account->id(),
      'changed' => $now,
      'changed_by' => (int) $account->id(),
    ])->execute();

    if ((int) $context['is_exception'] !== 1) {
      $this->database->update('brebo_calculation_subcalculation_application_object')
        ->fields(['is_exception' => 1])
        ->condition('id', $applicationObjectId)
        ->execute();
    }
    return $id;
  }

  /** @return array<string,float> */
  public function objectLineTotals(int $applicationObjectId): array {
    $rows = $this->database->select('brebo_calculation_subcalculation_object_exception_line', 'l')
      ->fields('l', ['quantity', 'labour_unit_cost', 'material_unit_cost', 'equipment_unit_cost', 'subcontracting_unit_cost', 'other_unit_cost'])
      ->condition('application_object_id', $applicationObjectId)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $totals = ['labour' => 0.0, 'material' => 0.0, 'equipment' => 0.0, 'subcontracting' => 0.0, 'other' => 0.0, 'direct' => 0.0];
    foreach ($rows as $row) {
      $q = (float) $row['quantity'];
      $totals['labour'] += $q * (float) $row['labour_unit_cost'];
      $totals['material'] += $q * (float) $row['material_unit_cost'];
      $totals['equipment'] += $q * (float) $row['equipment_unit_cost'];
      $totals['subcontracting'] += $q * (float) $row['subcontracting_unit_cost'];
      $totals['other'] += $q * (float) $row['other_unit_cost'];
    }
    $totals['direct'] = array_sum(array_intersect_key($totals, array_flip(['labour', 'material', 'equipment', 'subcontracting', 'other'])));
    return $totals;
  }

  /** @return array<string,mixed> */
  private function loadEditableContext(int $applicationObjectId, AccountInterface $account): array {
    if (!$account->hasPermission('edit brebo calculation workbench')) {
      throw new \RuntimeException('Missing calculation workbench edit permission.');
    }
    $query = $this->database->select('brebo_calculation_subcalculation_application_object', 'o');
    $query->join('brebo_calculation_subcalculation_application', 'a', 'a.id = o.application_id');
    $query->join('brebo_calculation_subcalculation', 's', 's.id = a.subcalculation_id');
    $query->join('brebo_calculation_version', 'v', 'v.calculation_id = s.calculation_id AND v.version = s.version');
    $query->fields('o');
    $query->addField('a', 'locked_at', 'application_locked_at');
    $query->addField('s', 'status', 'subcalculation_status');
    $query->addField('s', 'locked_at', 'subcalculation_locked_at');
    $query->addField('v', 'status', 'version_status');
    $query->addField('v', 'locked_at', 'version_locked_at');
    $row = $query->condition('o.id', $applicationObjectId)->execute()->fetchAssoc();
    if (!$row) {
      throw new \InvalidArgumentException('Application object not found.');
    }
    if ($row['application_locked_at'] !== NULL || $row['subcalculation_locked_at'] !== NULL || $row['version_locked_at'] !== NULL || $row['subcalculation_status'] !== 'draft' || $row['version_status'] !== 'draft') {
      throw new \RuntimeException('Exception lines can only be changed in an unlocked draft calculation.');
    }
    return $row;
  }

  private function nextSortOrder(int $applicationObjectId): int {
    $query = $this->database->select('brebo_calculation_subcalculation_object_exception_line', 'l');
    $query->addExpression('MAX(sort_order)', 'max_order');
    $max = $query->condition('application_object_id', $applicationObjectId)->execute()->fetchField();
    return ((int) $max) + 10;
  }
}
