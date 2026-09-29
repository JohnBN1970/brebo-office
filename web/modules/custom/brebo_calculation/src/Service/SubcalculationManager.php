<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\SubcalculationRepositoryInterface;
use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;

/** Manages reusable subcalculations and their project applications. */
final class SubcalculationManager {

  public function __construct(private readonly SubcalculationRepositoryInterface $repository, private readonly CalculationAccessGatewayInterface $accessGateway) {}

  /** @return list<array<string,mixed>> */
  public function list(int $calculationId, string $version): array {
    return $this->repository->subcalculations($calculationId, $version);
  }

  /** @return array<string,mixed>|null */
  public function get(int $subcalculationId): ?array {
    return $this->repository->subcalculation($subcalculationId);
  }

  /** @return list<array<string,mixed>> */
  public function scopes(int $subcalculationId): array {
    return $this->repository->scopes($subcalculationId);
  }


  /** @return list<array<string,mixed>> */
  public function selectedScopes(int $subcalculationId): array {
    return $this->repository->selectedScopes($subcalculationId);
  }

  /** @return list<array<string,mixed>> */
  public function structure(int $calculationId, string $version): array {
    return $this->repository->structure($calculationId, $version);
  }

  /** @return list<array<string,mixed>> */
  public function rowDomains(int $calculationId, string $version): array {
    return $this->repository->rowDomains($calculationId, $version);
  }

  public function removeScope(int $scopeId): void {
    $this->repository->deleteScope($scopeId);
  }

  /** @return list<array<string,mixed>> */
  public function applications(int $subcalculationId): array {
    return $this->repository->applications($subcalculationId);
  }

  /** @return list<array<string,mixed>> */
  public function applicationObjects(int $applicationId): array {
    return $this->repository->applicationObjectsDetailed($applicationId);
  }

  public function scopeCount(int $subcalculationId): int { return $this->repository->scopeCount($subcalculationId); }
  public function applicationCount(int $subcalculationId): int { return $this->repository->applicationCount($subcalculationId); }
  public function exceptionObjectCount(int $applicationId): int { return $this->repository->exceptionObjectCount($applicationId); }
  /** @return array{object_count:int,factor_sum:float} */
  public function applicationObjectStats(int $applicationId): array {
    return $this->repository->applicationObjectStats($applicationId);
  }


  /** @param array<string,mixed> $values */
  public function create(int $calculationId, string $version, array $values, int $actorId): int {
    $this->assertEditableCalculation($calculationId, $version, $actorId);
    $now = time();
    return $this->repository->insertSubcalculation([
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
      'created_by' => $actorId,
      'changed' => $now,
      'changed_by' => $actorId,
    ]);
  }

  public function addScope(int $subcalculationId, string $scopeType, string $scopeRef, float $multiplier, int $actorId): int {
    $sub = $this->loadEditableSubcalculation($subcalculationId, $actorId);
    if (!in_array($scopeType, ['structure', 'line'], TRUE)) {
      throw new \InvalidArgumentException('Unsupported subcalculation scope type.');
    }
    $scopeRef = trim($scopeRef);
    if ($scopeRef === '') {
      throw new \InvalidArgumentException('Scope reference is required.');
    }
    $this->assertScopeBelongsToCalculation($sub, $scopeType, $scopeRef);
    return $this->repository->insertScope([
      'subcalculation_id' => $subcalculationId,
      'scope_type' => $scopeType,
      'scope_ref' => $scopeRef,
      'multiplier' => max(0, $multiplier),
      'sort_order' => $this->repository->nextScopeOrder($subcalculationId),
      'created' => time(),
      'created_by' => $actorId,
    ]);
  }

  /** @param array<string,mixed> $values */
  public function createApplication(int $subcalculationId, array $values, int $actorId): int {
    $this->loadEditableSubcalculation($subcalculationId, $actorId);
    $now = time();
    return $this->repository->insertApplication([
      'subcalculation_id' => $subcalculationId,
      'application_type' => (string) ($values['application_type'] ?? 'manual'),
      'application_ref' => trim((string) ($values['application_ref'] ?? '')) ?: NULL,
      'project_ref' => trim((string) ($values['project_ref'] ?? '')) ?: NULL,
      'quantity' => max(0, (float) ($values['quantity'] ?? 1)),
      'status' => 'draft',
      'created' => $now,
      'created_by' => $actorId,
      'changed' => $now,
      'changed_by' => $actorId,
    ]);
  }

  /** @param array<string,float|int|string|null> $exceptionCosts */
  public function addApplicationObject(int $applicationId, string $objectType, string $objectRef, float $factor, bool $exception, ?string $exceptionPayload, int $actorId, array $exceptionCosts = []): int {
    $application = $this->repository->application($applicationId);
    if (!$application || $application['locked_at'] !== NULL) {
      throw new \RuntimeException('Application is missing or locked.');
    }
    $this->loadEditableSubcalculation((int) $application['subcalculation_id'], $actorId);
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
    $isException = $exception || array_sum($costs) > 0.000001 || trim((string) $exceptionPayload) !== '';
    return $this->repository->insertApplicationObject([
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
      'created_by' => $actorId,
    ]);
  }

  /** @return array<string,float> */
  public function totals(int $subcalculationId): array {
    $sub = $this->repository->subcalculation($subcalculationId);
    if (!$sub) {
      throw new \InvalidArgumentException('Subcalculation not found.');
    }
    $totals = ['labour' => 0.0, 'material' => 0.0, 'equipment' => 0.0, 'subcontracting' => 0.0, 'other' => 0.0, 'direct' => 0.0];
    $scopes = $this->repository->scopes($subcalculationId);
    $rowIds = [];
    foreach ($scopes as $scope) {
      if ($scope['scope_type'] === 'line') {
        $rowIds[(int) $scope['scope_ref']] = (float) $scope['multiplier'];
        continue;
      }
      foreach ($this->repository->rowIdsForParagraph((int) $sub['calculation_id'], (string) $sub['version'], (string) $scope['scope_ref']) as $rowId) {
        $rowIds[(int) $rowId] = (float) $scope['multiplier'];
      }
    }
    foreach ($rowIds as $rowId => $multiplier) {
      $row = $this->repository->rowCosts((int) $sub['calculation_id'], (string) $sub['version'], $rowId);
      if (!$row) {
        continue;
      }
      foreach (['labour', 'material', 'equipment', 'subcontracting', 'other'] as $carrier) {
        $totals[$carrier] += (float) $row[$carrier . '_unit_cost'] * $multiplier;
      }
    }
    $totals['direct'] = $totals['labour'] + $totals['material'] + $totals['equipment'] + $totals['subcontracting'] + $totals['other'];
    return $totals;
  }

  /** @return array<string,float> */
  public function applicationTotals(int $applicationId): array {
    $application = $this->repository->application($applicationId);
    if (!$application) {
      throw new \InvalidArgumentException('Application not found.');
    }
    $unit = $this->totals((int) $application['subcalculation_id']);
    $objects = $this->repository->applicationObjects($applicationId);

    $result = [
      'base' => $unit['direct'] * (float) $application['quantity'],
      'legacy_exception_labour' => 0.0,
      'legacy_exception_material' => 0.0,
      'legacy_exception_equipment' => 0.0,
      'legacy_exception_subcontracting' => 0.0,
      'legacy_exception_other' => 0.0,
      'legacy_exceptions' => 0.0,
      'line_exception_labour' => 0.0,
      'line_exception_material' => 0.0,
      'line_exception_equipment' => 0.0,
      'line_exception_subcontracting' => 0.0,
      'line_exception_other' => 0.0,
      'line_exceptions' => 0.0,
      'exceptions' => 0.0,
      'total' => 0.0,
    ];

    $factors = [];
    foreach ($objects as $object) {
      $objectId = (int) $object['id'];
      $factor = (float) $object['factor'];
      $factors[$objectId] = $factor;
      foreach (['labour', 'material', 'equipment', 'subcontracting', 'other'] as $carrier) {
        $result['legacy_exception_' . $carrier] += (float) $object['exception_' . $carrier] * $factor;
      }
    }

    if ($factors) {
      foreach ($this->repository->exceptionLines(array_keys($factors)) as $line) {
        $factor = $factors[(int) $line['application_object_id']] ?? 0.0;
        $quantity = (float) $line['quantity'];
        foreach (['labour', 'material', 'equipment', 'subcontracting', 'other'] as $carrier) {
          $result['line_exception_' . $carrier] += $factor * $quantity * (float) $line[$carrier . '_unit_cost'];
        }
      }
    }

    foreach (['labour', 'material', 'equipment', 'subcontracting', 'other'] as $carrier) {
      $result['legacy_exceptions'] += $result['legacy_exception_' . $carrier];
      $result['line_exceptions'] += $result['line_exception_' . $carrier];
    }
    $result['exceptions'] = $result['legacy_exceptions'] + $result['line_exceptions'];
    $result['total'] = $result['base'] + $result['exceptions'];
    return $result;
  }

  /** @return array<string,mixed> */
  private function loadEditableSubcalculation(int $subcalculationId, int $actorId): array {
    $sub = $this->repository->subcalculation($subcalculationId);
    if (!$sub || $sub['status'] !== 'draft' || $sub['locked_at'] !== NULL) {
      throw new \RuntimeException('Only unlocked draft subcalculations may be changed.');
    }
    $this->assertEditableCalculation((int) $sub['calculation_id'], (string) $sub['version'], $actorId);
    return $sub;
  }

  private function assertEditableCalculation(int $calculationId, string $version, int $actorId): void {
    $this->accessGateway->assertCanEditWorkbench($calculationId, $actorId);
    if (!$this->repository->isEditableVersion($calculationId, $version)) {
      throw new \RuntimeException('Only unlocked draft calculation versions may be changed.');
    }
  }

  /** @param array<string,mixed> $sub */
  private function assertScopeBelongsToCalculation(array $sub, string $scopeType, string $scopeRef): void {
    if (!$this->repository->scopeExists((int) $sub['calculation_id'], (string) $sub['version'], $scopeType, $scopeRef)) {
      throw new \InvalidArgumentException('Scope does not belong to the subcalculation source version.');
    }
  }

}
