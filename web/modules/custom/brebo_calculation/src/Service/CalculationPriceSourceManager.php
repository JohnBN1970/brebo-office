<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationPriceSourceRepositoryInterface;
use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;

/** Guarded creation and approval of auditable calculation price sources. */
final class CalculationPriceSourceManager {

  private const COST_FIELDS = [
    'labour' => 'labour_unit_cost',
    'material' => 'material_unit_cost',
    'equipment' => 'equipment_unit_cost',
    'subcontracting' => 'subcontracting_unit_cost',
    'other' => 'other_unit_cost',
  ];

  public function __construct(private readonly CalculationPriceSourceRepositoryInterface $repository, private readonly CalculationAccessGatewayInterface $accessGateway) {}

  /** @param array<string,mixed> $values */
  public function createForLine(int $calculationId, string $version, int $rowId, array $values, int $actorId): int {
    $this->assertEditable($calculationId, $version, $rowId, $actorId);
    $costCarrier = $this->normalizeCostCarrier((string) ($values['cost_carrier'] ?? 'subcontracting'));
    $now = time();
    $source = [
      'calculation_id' => $calculationId,
      'version' => $version,
      'source_type' => (string) ($values['source_type'] ?? 'manual'),
      'source_ref' => trim((string) ($values['source_ref'] ?? '')) ?: NULL,
      'file_id' => !empty($values['file_id']) ? (int) $values['file_id'] : NULL,
      'email_message_id' => trim((string) ($values['email_message_id'] ?? '')) ?: NULL,
      'supplier_ref' => trim((string) ($values['supplier_ref'] ?? '')) ?: NULL,
      'supplier_name' => trim((string) ($values['supplier_name'] ?? '')) ?: NULL,
      'supplier_email' => trim((string) ($values['supplier_email'] ?? '')) ?: NULL,
      'offer_number' => trim((string) ($values['offer_number'] ?? '')) ?: NULL,
      'offer_date' => trim((string) ($values['offer_date'] ?? '')) ?: NULL,
      'valid_until' => trim((string) ($values['valid_until'] ?? '')) ?: NULL,
      'currency' => strtoupper(trim((string) ($values['currency'] ?? 'EUR')) ?: 'EUR'),
      'quoted_total' => ($values['quoted_total'] ?? '') !== '' ? (float) $values['quoted_total'] : NULL,
      'status' => 'received',
      'extraction_status' => (string) ($values['extraction_status'] ?? 'review'),
      'scope_summary' => trim((string) ($values['scope_summary'] ?? '')) ?: NULL,
      'conditions_summary' => trim((string) ($values['conditions_summary'] ?? '')) ?: NULL,
      'internal_note' => trim((string) ($values['internal_note'] ?? '')) ?: NULL,
      'created' => $now,
      'created_by' => $actorId,
      'changed' => $now,
      'changed_by' => $actorId,
    ];
    $line = [
      'calculation_id' => $calculationId,
      'version' => $version,
      'row_id' => $rowId,
      'source_line_ref' => 'cost_carrier:' . $costCarrier,
      'extracted_description' => trim((string) ($values['extracted_description'] ?? '')) ?: NULL,
      'extracted_quantity' => ($values['extracted_quantity'] ?? '') !== '' ? (float) $values['extracted_quantity'] : NULL,
      'extracted_unit' => trim((string) ($values['extracted_unit'] ?? '')) ?: NULL,
      'extracted_unit_price' => ($values['extracted_unit_price'] ?? '') !== '' ? (float) $values['extracted_unit_price'] : NULL,
      'extracted_total' => ($values['extracted_total'] ?? '') !== '' ? (float) $values['extracted_total'] : NULL,
      'proposed_oa_unit_cost' => ($values['proposed_unit_cost'] ?? '') !== '' ? (float) $values['proposed_unit_cost'] : NULL,
      'approval_status' => 'review',
      'is_active_source' => 0,
      'created' => $now,
      'created_by' => $actorId,
    ];
    return $this->repository->createSourceWithLine($source, $line);
  }

  public function approveForLine(int $calculationId, string $version, int $rowId, int $sourceId, string $costCarrier, float $unitCost, ?string $note, int $actorId): void {
    $this->assertEditable($calculationId, $version, $rowId, $actorId);
    $costCarrier = $this->normalizeCostCarrier($costCarrier);
    if ($unitCost < 0) {
      throw new \InvalidArgumentException('Unit cost cannot be negative.');
    }

    $mapping = $this->repository->mappingId($sourceId, $calculationId, $version, $rowId);
    if (!$mapping) {
      throw new \InvalidArgumentException('Price source is not linked to this calculation row.');
    }

    $targetField = self::COST_FIELDS[$costCarrier];
    $approval = [
      'approved_oa_unit_cost' => $unitCost,
      'approval_status' => 'approved',
      'is_active_source' => 1,
      'approval_note' => trim('Kostendrager: ' . $costCarrier . '. ' . ($note ?? '')),
      'approved' => time(),
      'approved_by' => $actorId,
      'source_line_ref' => 'cost_carrier:' . $costCarrier,
    ];
    $this->repository->approveSource(
      $calculationId,
      $version,
      $rowId,
      $sourceId,
      (int) $mapping,
      $costCarrier,
      $targetField,
      $unitCost,
      $approval,
    );
  }

  private function normalizeCostCarrier(string $costCarrier): string {
    if (!isset(self::COST_FIELDS[$costCarrier])) {
      throw new \InvalidArgumentException('Unknown calculation cost carrier.');
    }
    return $costCarrier;
  }

  private function assertEditable(int $calculationId, string $version, int $rowId, int $actorId): void {
    $this->accessGateway->assertCanEditWorkbench($calculationId, $actorId);
    if (!$this->repository->isEditableVersion($calculationId, $version)) {
      throw new \RuntimeException('Only unlocked draft calculation versions may be changed.');
    }
    if (!$this->repository->rowExists($calculationId, $version, $rowId)) {
      throw new \InvalidArgumentException('Calculation row does not belong to this version.');
    }
  }

}
