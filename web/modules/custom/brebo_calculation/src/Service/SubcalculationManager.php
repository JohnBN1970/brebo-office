<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/** Manages reusable subcalculations and their project applications. */
final class SubcalculationManager {

  public function __construct(private readonly Connection $database) {}

  /** @param array<string,mixed> $values */
  public function create(int $calculationId, string $version, array $values, AccountInterface $account): int {
    $this->assertEditableCalculation($calculationId, $version, $account);
    $now = time();
    return (int) $this->database->insert('brebo_calculation_subcalculation')->fields([
      'calculation_id' => $calculationId,
      'version' => $version,
      'code' => trim((string) ($values['code'] ?? '')) ?: NULL,
      'label' => trim((string) ($values['label'] ?? '')),
      'subcalculation_type' => (string) ($values['subcalculation_type'] ?? 'manual'),
      'status' => 'draft',
      'unit_label' => trim((string) ($values['unit_label'] ?? '')) ?: NULL,
      'base_quantity' => max(0.0001, (float) ($values['base_quantity'] ?? 1)),
      'context_type' => trim((string) ($values['context_type'] ?? '')) ?: NULL,
      'context_ref' => trim((string) ($values['context_ref'] ?? '')) ?: NULL,
      'created' => $now,
      'created_by' => (int) $account->id(),
      'changed' => $now,
      'changed_by' => (int) $account->id(),
    ])->execute();
  }

  public function addScope(int $subcalculationId, string $scopeType, string $scopeRef, float $multiplier, AccountInterface $account): int {
    $sub = $this->loadEditableSubcalculation($subcalculationId, $account);
    if (!in_array($scopeType, ['structure', 'line'], TRUE)) {
      throw new \InvalidArgumentException('Unsupported subcalculation scope type.');
    }
    $scopeRef = trim($scopeRef);
    if ($scopeRef === '') {
      throw new \InvalidArgumentException('Scope reference is required.');
    }
    $this->assertScopeBelongsToCalculation($sub, $scopeType, $scopeRef);
    return (int) $this->database->insert('brebo_calculation_subcalculation_scope')->fields([
      'subcalculation_id' => $subcalculationId,
      'scope_type' => $scopeType,
      'scope_ref' => $scopeRef,
      'multiplier' => max(0, $multiplier),
      'sort_order' => $this->nextScopeOrder($subcalculationId),
      'created' => time(),
      'created_by' => (int) $account->id(),
    ])->execute();
  }

  /** @param array<string,mixed> $values */
  public function createApplication(int $subcalculationId, array $values, AccountInterface $account): int {
    $this->loadEditableSubcalculation($subcalculationId, $account);
    $now = time();
    return (int) $this->database->insert('brebo_calculation_subcalculation_application')->fields([
      'subcalculation_id' => $subcalculationId,
      'application_type' => (string) ($values['application_type'] ?? 'manual'),
      'application_ref' => trim((string) ($values['application_ref'] ?? '')) ?: NULL,
      'project_ref' => trim((string) ($values['project_ref'] ?? '')) ?: NULL,
      'quantity' => max(0, (float) ($values['quantity'] ?? 1)),
      'status' => 'draft',
      'created' => $now,
      'created_by' => (int) $account->id(),
      'changed' => $now,
      'changed_by' => (int) $account->id(),
    ])->execute();
  }

  /**
   * Add one concrete canonical building/project object to an application.
   *
   * @param array<string,float|int|string|null> $exceptionCosts
   *   Optional additive cost deviations per cost carrier.
   */
  public function addApplicationObject(int $applicationId, string $objectType, string $objectRef, float $factor, bool $exception, ?string $exceptionPayload, AccountInterface $account, array $exceptionCosts = []): int {
    $application = $this->database->select('brebo_calculation_subcalculation_application', 'a')
      ->fields('a', ['subcalculation_id', 'locked_at'])
      ->condition('id', $applicationId)
      ->execute()->fetchAssoc();
    if (!$application || $application['locked_at'] !== NULL) {
      throw new \RuntimeException('Application is missing or locked.');
    }
    $this->loadEditableSubcalculation((int) $application['subcalculation_id'], $account);
    $objectRef = trim($objectRef);
    if ($objectRef === '') {
      throw new \InvalidArgumentException('Canonical object reference is required.');
    }

    $costs = [
      'exception_labour' => (float) ($exceptionCosts['exception_labour'] ?? 0),
      'exception_material' => (float) ($exceptionCosts['exception_material'] ?? 0),
      'exception_equipment' => (float) ($exceptionCosts['exception_equipment'] ?? 0),
      'exception_subcontracting' => (float) ($exceptionCosts['exception_subcontracting'] ?? 0),
      'exception_other' => (float) ($exceptionCosts['exception_other'] ?? 0),
    ];
    foreach ($costs as $value) {
      if ($value < 0) {
        throw new \InvalidArgumentException('Financial exception costs cannot be negative.');
      }
    }
    $hasFinancialException = array_sum($costs) > 0.000001;
    $isException = $exception || $hasFinancialException || trim((string) $exceptionPayload) !== '';

    return (int) $this->database->insert('brebo_calculation_subcalculation_application_object')->fields([
      'application_id' => $applicationId,
      'object_type' => trim($objectType),
      'object_ref' => $objectRef,
      'factor' => max(0, $factor),
      'is_exception' => $isException ? 1 : 0,
      'exception_payload' => trim((string) $exceptionPayload) ?: NULL,
      'exception_labour' => $costs['exception_labour'],
      'exception_material' => $costs['exception_material'],
      'exception_equipment' => $costs['exception_equipment'],
      'exception_subcontracting' => $costs['exception_subcontracting'],
      'exception_other' => $costs['exception_other'],
      'created' => time(),
      'created_by' => (int) $account->id(),
    ])->execute();
  }

  /** @return array<string,float> */
  public function totals(int $subcalculationId): array {
    $sub = $this->database->select('brebo_calculation_subcalculation', 's')
      ->fields('s', ['calculation_id', 'version'])
      ->condition('id', $subcalculationId)
      ->execute()->fetchAssoc();
    if (!$sub) {
      throw new \InvalidArgumentException('Subcalculation not found.');
    }

    $totals = ['labour' => 0.0, 'material' => 0.0, 'equipment' => 0.0, 'subcontracting' => 0.0, 'other' => 0.0, 'direct' => 0.0];
    $scopes = $this->database->select('brebo_calculation_subcalculation_scope', 'ss')
      ->fields('ss', ['scope_type', 'scope_ref', 'multiplier'])
      ->condition('subcalculation_id', $subcalculationId)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $lineIds = [];
    foreach ($scopes as $scope) {
      if ($scope['scope_type'] === 'line') {
        $lineIds[(int) $scope['scope_ref']] = (float) $scope['multiplier'];
        continue;
      }
      $structureKey = (string) $scope['scope_ref'];
      $query = $this->database->select('brebo_calculation_row_domain', 'r');
      $query->fields('r', ['calc_line_id']);
      $query->condition('r.calculation_id', (int) $sub['calculation_id']);
      $query->condition('r.version', (string) $sub['version']);
      $query->condition('r.paragraph_key', $structureKey);
      foreach ($query->execute()->fetchCol() as $lineId) {
        $lineIds[(int) $lineId] = (float) $scope['multiplier'];
      }
    }

    foreach ($lineIds as $lineId => $multiplier) {
      $row = $this->database->select('brebo_calculation_row_domain', 'r')
        ->fields('r', ['labour_unit_cost', 'material_unit_cost', 'equipment_unit_cost', 'subcontracting_unit_cost', 'other_unit_cost'])
        ->condition('calculation_id', (int) $sub['calculation_id'])
        ->condition('version', (string) $sub['version'])
        ->condition('calc_line_id', $lineId)
        ->execute()->fetchAssoc();
      if (!$row) {
        continue;
      }
      $totals['labour'] += (float) $row['labour_unit_cost'] * $multiplier;
      $totals['material'] += (float) $row['material_unit_cost'] * $multiplier;
      $totals['equipment'] += (float) $row['equipment_unit_cost'] * $multiplier;
      $totals['subcontracting'] += (float) $row['subcontracting_unit_cost'] * $multiplier;
      $totals['other'] += (float) $row['other_unit_cost'] * $multiplier;
    }
    $totals['direct'] = $totals['labour'] + $totals['material'] + $totals['equipment'] + $totals['subcontracting'] + $totals['other'];
    return $totals;
  }

  /** @return array<string,float> */
  public function applicationTotals(int $applicationId): array {
    $application = $this->database->select('brebo_calculation_subcalculation_application', 'a')
      ->fields('a', ['subcalculation_id', 'quantity'])
      ->condition('id', $applicationId)
      ->execute()->fetchAssoc();
    if (!$application) {
      throw new \InvalidArgumentException('Application not found.');
    }
    $unit = $this->totals((int) $application['subcalculation_id']);
    $objects = $this->database->select('brebo_calculation_subcalculation_application_object', 'o')
      ->fields('o', ['factor', 'exception_labour', 'exception_material', 'exception_equipment', 'exception_subcontracting', 'exception_other'])
      ->condition('application_id', $applicationId)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $result = [
      'base' => $unit['direct'] * (float) $application['quantity'],
      'exception_labour' => 0.0,
      'exception_material' => 0.0,
      'exception_equipment' => 0.0,
      'exception_subcontracting' => 0.0,
      'exception_other' => 0.0,
      'exceptions' => 0.0,
      'total' => 0.0,
    ];
    foreach ($objects as $object) {
      $factor = (float) $object['factor'];
      foreach (['labour', 'material', 'equipment', 'subcontracting', 'other'] as $carrier) {
        $key = 'exception_' . $carrier;
        $result[$key] += (float) $object[$key] * $factor;
      }
    }
    $result['exceptions'] = $result['exception_labour'] + $result['exception_material'] + $result['exception_equipment'] + $result['exception_subcontracting'] + $result['exception_other'];
    $result['total'] = $result['base'] + $result['exceptions'];
    return $result;
  }

  /** @return array<string,mixed> */
  private function loadEditableSubcalculation(int $subcalculationId, AccountInterface $account): array {
    if (!$account->hasPermission('edit brebo calculation workbench')) {
      throw new \RuntimeException('Missing calculation workbench edit permission.');
    }
    $sub = $this->database->select('brebo_calculation_subcalculation', 's')
      ->fields('s')
      ->condition('id', $subcalculationId)
      ->execute()->fetchAssoc();
    if (!$sub || $sub['status'] !== 'draft' || $sub['locked_at'] !== NULL) {
      throw new \RuntimeException('Only unlocked draft subcalculations may be changed.');
    }
    $this->assertEditableCalculation((int) $sub['calculation_id'], (string) $sub['version'], $account);
    return $sub;
  }

  private function assertEditableCalculation(int $calculationId, string $version, AccountInterface $account): void {
    if (!$account->hasPermission('edit brebo calculation workbench')) {
      throw new \RuntimeException('Missing calculation workbench edit permission.');
    }
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['status', 'locked_at'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    if (!$row || $row['status'] !== 'draft' || $row['locked_at'] !== NULL) {
      throw new \RuntimeException('Only unlocked draft calculation versions may be changed.');
    }
  }

  /** @param array<string,mixed> $sub */
  private function assertScopeBelongsToCalculation(array $sub, string $scopeType, string $scopeRef): void {
    if ($scopeType === 'line') {
      $exists = $this->database->select('brebo_calculation_row_domain', 'r')
        ->condition('calculation_id', (int) $sub['calculation_id'])
        ->condition('version', (string) $sub['version'])
        ->condition('calc_line_id', (int) $scopeRef)
        ->countQuery()->execute()->fetchField();
    }
    else {
      $exists = $this->database->select('brebo_calculation_structure', 's')
        ->condition('calculation_id', (int) $sub['calculation_id'])
        ->condition('version', (string) $sub['version'])
        ->condition('node_key', $scopeRef)
        ->countQuery()->execute()->fetchField();
    }
    if (!(int) $exists) {
      throw new \InvalidArgumentException('Scope does not belong to the subcalculation source version.');
    }
  }

  private function nextScopeOrder(int $subcalculationId): int {
    $max = $this->database->select('brebo_calculation_subcalculation_scope', 's')
      ->condition('subcalculation_id', $subcalculationId)
      ->addExpression('MAX(sort_order)', 'max_order')
      ->execute()->fetchField();
    return ((int) $max) + 10;
  }
}
