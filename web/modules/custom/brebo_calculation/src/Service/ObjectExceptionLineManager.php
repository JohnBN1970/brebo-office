<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\ObjectExceptionLineRepositoryInterface;
use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;

/** Manages additive calculation lines for concrete object deviations. */
final class ObjectExceptionLineManager {

  public function __construct(private readonly ObjectExceptionLineRepositoryInterface $repository, private readonly CalculationAccessGatewayInterface $accessGateway) {}

  /** @param array<string,mixed> $values */
  public function addLine(int $applicationObjectId, array $values, int $actorId): int {
    $context = $this->loadEditableContext($applicationObjectId, $actorId);
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
    $id = $this->repository->insertLine([
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
      'sort_order' => $this->repository->nextSortOrder($applicationObjectId),
      'created' => $now,
      'created_by' => $actorId,
      'changed' => $now,
      'changed_by' => $actorId,
    ]);

    if ((int) $context['is_exception'] !== 1) {
      $this->repository->markApplicationObjectException($applicationObjectId);
    }
    return $id;
  }

  /** @return array<string,float> */
  public function objectLineTotals(int $applicationObjectId): array {
    $rows = $this->repository->lines($applicationObjectId);
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
  private function loadEditableContext(int $applicationObjectId, int $actorId): array {
    $row = $this->repository->editableContext($applicationObjectId);
    if (!$row) {
      throw new \InvalidArgumentException('Application object not found.');
    }
    $this->accessGateway->assertCanEditWorkbench((int) $row['calculation_id'], $actorId);

    if ($row['application_locked_at'] !== NULL || $row['subcalculation_locked_at'] !== NULL || $row['version_locked_at'] !== NULL || $row['subcalculation_status'] !== 'draft' || $row['version_status'] !== 'draft') {
      throw new \RuntimeException('Exception lines can only be changed in an unlocked draft calculation.');
    }
    return $row;
  }

}
