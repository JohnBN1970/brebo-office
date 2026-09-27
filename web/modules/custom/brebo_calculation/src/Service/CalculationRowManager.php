<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;
use Drupal\brebo_calculation\Contract\CalculationLineLegacyGatewayInterface;
use Drupal\Core\Database\Connection;

/** Guarded mutations for editable calculation rows. */
final class CalculationRowManager {

  public function __construct(
    private readonly Connection $database,
    private readonly CalculationLineLegacyGatewayInterface $legacyLineGateway,
    private readonly CalculationAccessGatewayInterface $accessGateway,
    private readonly CalculationRowIdentityGenerator $rowIdentityGenerator,
  ) {}

  public function add(int $calculationId, string $version, string $paragraphKey, int $actorId): int {
    $this->assertEditable($calculationId, $version, $actorId);
    $this->assertLeafParagraph($calculationId, $version, $paragraphKey);

    $legacyElementId = $this->legacyLineGateway->resolveElementId($calculationId, $paragraphKey);
    if ($legacyElementId === NULL) {
      throw new \RuntimeException('No legacy calculation element is mapped to this paragraph yet.');
    }

    $transaction = $this->database->startTransaction();
    try {
      $lineId = $this->legacyLineGateway->create($legacyElementId, $actorId);

      $rowId = $this->rowIdentityGenerator->next();
      $this->database->insert('brebo_calculation_row_domain')->fields([
        'row_id' => $rowId,
        'calc_line_id' => $lineId,
        'calculation_id' => $calculationId,
        'version' => $version,
        'paragraph_key' => $paragraphKey,
        'rule_type' => 'normal',
        'description' => 'Nieuwe calculatieregel',
        'contract_quantity' => 1,
        'actual_quantity' => NULL,
        'unit' => 'post',
        'budget_hours' => 0,
        'labour_rate' => 0,
        'labour_unit_cost' => 0,
        'material_unit_cost' => 0,
        'equipment_unit_cost' => 0,
        'subcontracting_unit_cost' => 0,
        'other_unit_cost' => 0,
      ])->execute();
      return $rowId;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Update one workbench row from quick-entry values.
   *
   * @param array<string, float|int> $unitCosts
   *   Direct unit costs keyed by labour, material, equipment,
   *   subcontracting and other.
   */
  public function updateQuickEntry(
    int $calculationId,
    string $version,
    int $rowId,
    string $description,
    string $unit,
    float $quantity,
    array $unitCosts,
    int $actorId,
  ): void {
    $this->assertEditable($calculationId, $version, $actorId);
    $domain = $this->domainRow($calculationId, $version, $rowId);

    $description = trim($description);
    $unit = trim($unit);
    if ($description === '') {
      throw new \InvalidArgumentException('Omschrijving is verplicht.');
    }
    if ($unit === '') {
      throw new \InvalidArgumentException('Eenheid is verplicht.');
    }
    if ($quantity < 0) {
      throw new \InvalidArgumentException('Aantal mag niet negatief zijn.');
    }

    $costs = [
      'labour_unit_cost' => $this->nonNegativeCost($unitCosts, 'labour'),
      'material_unit_cost' => $this->nonNegativeCost($unitCosts, 'material'),
      'equipment_unit_cost' => $this->nonNegativeCost($unitCosts, 'equipment'),
      'subcontracting_unit_cost' => $this->nonNegativeCost($unitCosts, 'subcontracting'),
      'other_unit_cost' => $this->nonNegativeCost($unitCosts, 'other'),
    ];

    $transaction = $this->database->startTransaction();
    try {
      $this->legacyLineGateway->updateQuickEntry((int) $domain['calc_line_id'], $description, $unit, $quantity, $costs);

      $this->database->update('brebo_calculation_row_domain')
        ->fields($costs + [
          'description' => $description,
          'contract_quantity' => $quantity,
          'unit' => $unit,
        ])
        ->condition('row_id', $rowId)
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->execute();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  public function duplicate(int $calculationId, string $version, int $rowId, int $actorId): int {
    $this->assertEditable($calculationId, $version, $actorId);
    $domain = $this->domainRow($calculationId, $version, $rowId);
    $transaction = $this->database->startTransaction();
    try {
      $copyLegacyLineId = $this->legacyLineGateway->duplicate((int) $domain['calc_line_id'], $actorId);
      unset($domain['row_id'], $domain['calc_line_id'], $domain['calculation_id'], $domain['version']);
      $copyRowId = $this->rowIdentityGenerator->next();
      $domain['row_id'] = $copyRowId;
      $domain['calc_line_id'] = $copyLegacyLineId;
      $domain['calculation_id'] = $calculationId;
      $domain['version'] = $version;
      $this->database->insert('brebo_calculation_row_domain')->fields($domain)->execute();
      return $copyRowId;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  public function delete(int $calculationId, string $version, int $rowId, int $actorId): void {
    $this->assertEditable($calculationId, $version, $actorId);
    $domain = $this->domainRow($calculationId, $version, $rowId);
    $transaction = $this->database->startTransaction();
    try {
      $this->database->delete('brebo_calculation_row_domain')
        ->condition('row_id', $rowId)
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->execute();
      $this->legacyLineGateway->delete((int) $domain['calc_line_id']);
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  public function move(int $calculationId, string $version, int $rowId, string $targetParagraphKey, int $actorId): void {
    $this->assertEditable($calculationId, $version, $actorId);
    $domain = $this->domainRow($calculationId, $version, $rowId);
    $this->assertLeafParagraph($calculationId, $version, $targetParagraphKey);

    $targetElementId = $this->legacyLineGateway->resolveElementId($calculationId, $targetParagraphKey);
    if ($targetElementId === NULL) {
      throw new \RuntimeException('Target paragraph has no safe legacy element mapping.');
    }

    $transaction = $this->database->startTransaction();
    try {
      $this->legacyLineGateway->move((int) $domain['calc_line_id'], $targetElementId);

      $this->database->update('brebo_calculation_row_domain')
        ->fields(['paragraph_key' => $targetParagraphKey])
        ->condition('row_id', $rowId)
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->execute();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /** @return array<string,mixed> */
  private function domainRow(int $calculationId, string $version, int $rowId): array {
    $row = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r')
      ->condition('row_id', $rowId)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    if (!$row) {
      throw new \InvalidArgumentException('Row does not belong to this calculation version.');
    }
    return $row;
  }

  private function assertEditable(int $calculationId, string $version, int $actorId): void {
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['locked_at', 'status'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    if (!$row || $row['locked_at'] !== NULL || $row['status'] !== 'draft') {
      throw new \RuntimeException('Only unlocked draft calculation versions may be changed.');
    }
    $this->accessGateway->assertCanEditWorkbench($calculationId, $actorId);
  }

  private function assertLeafParagraph(int $calculationId, string $version, string $paragraphKey): void {
    $node = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s', ['node_key', 'node_type'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('node_key', $paragraphKey)
      ->execute()->fetchAssoc();
    if (!$node || $node['node_type'] !== 'paragraph') {
      throw new \InvalidArgumentException('Rows can only be attached to paragraphs.');
    }
    $children = (int) $this->database->select('brebo_calculation_structure', 's')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('parent_key', $paragraphKey)
      ->countQuery()->execute()->fetchField();
    if ($children > 0) {
      throw new \RuntimeException('Only leaf paragraphs may contain calculation rows.');
    }
  }


  /** @param array<string, float|int> $unitCosts */
  private function nonNegativeCost(array $unitCosts, string $key): float {
    $value = (float) ($unitCosts[$key] ?? 0.0);
    if ($value < 0) {
      throw new \InvalidArgumentException('Eenheidskosten mogen niet negatief zijn.');
    }
    return $value;
  }


}
