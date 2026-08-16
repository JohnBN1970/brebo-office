<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/** Guarded creation and approval of auditable calculation price sources. */
final class CalculationPriceSourceManager {

  private const COST_FIELDS = [
    'labour' => 'labour_unit_cost',
    'material' => 'material_unit_cost',
    'equipment' => 'equipment_unit_cost',
    'subcontracting' => 'subcontracting_unit_cost',
    'other' => 'other_unit_cost',
  ];

  public function __construct(private readonly Connection $database) {}

  /** @param array<string,mixed> $values */
  public function createForLine(int $calculationId, string $version, int $lineId, array $values, AccountInterface $account): int {
    $this->assertEditable($calculationId, $version, $lineId, $account);
    $costCarrier = $this->normalizeCostCarrier((string) ($values['cost_carrier'] ?? 'subcontracting'));
    $now = time();
    $transaction = $this->database->startTransaction();
    try {
      $sourceId = (int) $this->database->insert('brebo_calculation_price_source')->fields([
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
        'created_by' => (int) $account->id(),
        'changed' => $now,
        'changed_by' => (int) $account->id(),
      ])->execute();

      // The legacy column name proposed_oa_unit_cost is retained temporarily for
      // migration compatibility; source_line_ref carries the explicit carrier.
      $this->database->insert('brebo_calculation_price_source_line')->fields([
        'price_source_id' => $sourceId,
        'calculation_id' => $calculationId,
        'version' => $version,
        'calc_line_id' => $lineId,
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
        'created_by' => (int) $account->id(),
      ])->execute();
      return $sourceId;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  public function approveForLine(int $calculationId, string $version, int $lineId, int $sourceId, string $costCarrier, float $unitCost, ?string $note, AccountInterface $account): void {
    $this->assertEditable($calculationId, $version, $lineId, $account);
    $costCarrier = $this->normalizeCostCarrier($costCarrier);
    if ($unitCost < 0) {
      throw new \InvalidArgumentException('Unit cost cannot be negative.');
    }

    $mapping = $this->database->select('brebo_calculation_price_source_line', 'm')
      ->fields('m', ['id'])
      ->condition('price_source_id', $sourceId)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('calc_line_id', $lineId)
      ->execute()->fetchField();
    if (!$mapping) {
      throw new \InvalidArgumentException('Price source is not linked to this calculation row.');
    }

    $targetField = self::COST_FIELDS[$costCarrier];
    $transaction = $this->database->startTransaction();
    try {
      $this->database->update('brebo_calculation_price_source_line')
        ->fields(['is_active_source' => 0])
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->condition('calc_line_id', $lineId)
        ->condition('source_line_ref', 'cost_carrier:' . $costCarrier)
        ->execute();
      $this->database->update('brebo_calculation_price_source_line')
        ->fields([
          'approved_oa_unit_cost' => $unitCost,
          'approval_status' => 'approved',
          'is_active_source' => 1,
          'approval_note' => trim('Kostendrager: ' . $costCarrier . '. ' . ($note ?? '')),
          'approved' => time(),
          'approved_by' => (int) $account->id(),
          'source_line_ref' => 'cost_carrier:' . $costCarrier,
        ])
        ->condition('id', (int) $mapping)
        ->execute();
      $this->database->update('brebo_calculation_row_domain')
        ->fields([$targetField => $unitCost])
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->condition('calc_line_id', $lineId)
        ->execute();
      $this->database->update('brebo_calculation_price_source')
        ->fields(['status' => 'accepted', 'changed' => time(), 'changed_by' => (int) $account->id()])
        ->condition('id', $sourceId)
        ->execute();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  private function normalizeCostCarrier(string $costCarrier): string {
    if (!isset(self::COST_FIELDS[$costCarrier])) {
      throw new \InvalidArgumentException('Unknown calculation cost carrier.');
    }
    return $costCarrier;
  }

  private function assertEditable(int $calculationId, string $version, int $lineId, AccountInterface $account): void {
    if (!$account->hasPermission('edit brebo calculation workbench')) {
      throw new \RuntimeException('Missing calculation workbench edit permission.');
    }
    $versionRow = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['status', 'locked_at'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    if (!$versionRow || $versionRow['status'] !== 'draft' || $versionRow['locked_at'] !== NULL) {
      throw new \RuntimeException('Only unlocked draft calculation versions may be changed.');
    }
    $exists = $this->database->select('brebo_calculation_row_domain', 'r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('calc_line_id', $lineId)
      ->countQuery()->execute()->fetchField();
    if (!(int) $exists) {
      throw new \InvalidArgumentException('Calculation row does not belong to this version.');
    }
  }

}
