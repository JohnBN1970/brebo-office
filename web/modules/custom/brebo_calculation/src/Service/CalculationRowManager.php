<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;
use Drupal\brebo_calculation\Contract\CalculationLineLegacyGatewayInterface;
use Drupal\brebo_calculation\Contract\CalculationLegacyLineMirrorMapInterface;
use Drupal\brebo_calculation\Contract\CalculationLegacyLineMirrorPolicyInterface;
use Drupal\Core\Database\Connection;

/** Guarded mutations for editable calculation rows. */
final class CalculationRowManager {

  public function __construct(
    private readonly Connection $database,
    private readonly CalculationLineLegacyGatewayInterface $legacyLineGateway,
    private readonly CalculationAccessGatewayInterface $accessGateway,
    private readonly CalculationRowIdentityGenerator $rowIdentityGenerator,
    private readonly CalculationLegacyLineMirrorMapInterface $legacyMirrorMap,
    private readonly CalculationLegacyLineMirrorPolicyInterface $legacyMirrorPolicy,
  ) {}

  public function add(int $calculationId, string $version, string $paragraphKey, int $actorId): int {
    $this->assertEditable($calculationId, $version, $actorId);
    $this->assertLeafParagraph($calculationId, $version, $paragraphKey);

    $rowId = $this->rowIdentityGenerator->next();
    $this->database->insert('brebo_calculation_row_domain')->fields([
      'row_id' => $rowId,
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
      'sort_order' => $this->nextSortOrder($calculationId, $version, $paragraphKey),
      'labour_unit_cost' => 0,
      'material_unit_cost' => 0,
      'equipment_unit_cost' => 0,
      'subcontracting_unit_cost' => 0,
      'other_unit_cost' => 0,
    ])->execute();

    $this->createLegacyMirror($calculationId, $version, $rowId, $paragraphKey, $actorId);
    return $rowId;
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

    $legacyLineId = $this->legacyMirrorMap->legacyLineId($calculationId, $version, $rowId) ?? 0;
    if ($legacyLineId > 0) {
      $this->legacyLineGateway->updateQuickEntry($legacyLineId, $description, $unit, $quantity, $costs);
    }
  }

  public function duplicate(int $calculationId, string $version, int $rowId, int $actorId): int {
    $this->assertEditable($calculationId, $version, $actorId);
    $domain = $this->domainRow($calculationId, $version, $rowId);

    unset($domain['row_id'], $domain['calculation_id'], $domain['version']);
    $copyRowId = $this->rowIdentityGenerator->next();
    $domain['row_id'] = $copyRowId;
    $domain['sort_order'] = $this->nextSortOrder($calculationId, $version, (string) $domain['paragraph_key']);
    $domain['calculation_id'] = $calculationId;
    $domain['version'] = $version;
    $this->database->insert('brebo_calculation_row_domain')->fields($domain)->execute();

    $legacyLineId = $this->legacyMirrorMap->legacyLineId($calculationId, $version, $rowId) ?? 0;
    if ($legacyLineId > 0) {
      $copyLegacyLineId = $this->legacyLineGateway->duplicate($legacyLineId, $actorId);
      $this->legacyMirrorMap->attach($calculationId, $version, $copyRowId, $copyLegacyLineId);
    }
    else {
      $this->createLegacyMirror($calculationId, $version, $copyRowId, (string) $domain['paragraph_key'], $actorId);
    }

    return $copyRowId;
  }

  public function delete(int $calculationId, string $version, int $rowId, int $actorId): void {
    $this->assertEditable($calculationId, $version, $actorId);
    $domain = $this->domainRow($calculationId, $version, $rowId);
    $this->database->delete('brebo_calculation_row_domain')
      ->condition('row_id', $rowId)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute();

    $legacyLineId = $this->legacyMirrorMap->legacyLineId($calculationId, $version, $rowId) ?? 0;
    if ($legacyLineId > 0) {
      $this->legacyLineGateway->delete($legacyLineId);
    }
  }

  public function move(int $calculationId, string $version, int $rowId, string $targetParagraphKey, int $actorId): void {
    $this->assertEditable($calculationId, $version, $actorId);
    $domain = $this->domainRow($calculationId, $version, $rowId);
    $this->assertLeafParagraph($calculationId, $version, $targetParagraphKey);

    $this->database->update('brebo_calculation_row_domain')
      ->fields([
        'paragraph_key' => $targetParagraphKey,
        'sort_order' => $this->nextSortOrder($calculationId, $version, $targetParagraphKey),
      ])
      ->condition('row_id', $rowId)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute();

    $legacyLineId = $this->legacyMirrorMap->legacyLineId($calculationId, $version, $rowId) ?? 0;
    if ($legacyLineId > 0) {
      $targetElementId = $this->legacyLineGateway->resolveElementId($calculationId, $targetParagraphKey);
      if ($targetElementId !== NULL) {
        $this->legacyLineGateway->move($legacyLineId, $targetElementId);
      }
    }
  }

  private function createLegacyMirror(int $calculationId, string $version, int $rowId, string $paragraphKey, int $actorId): void {
    if (!$this->legacyMirrorPolicy->createLegacyMirrors()) {
      return;
    }
    $legacyElementId = $this->legacyLineGateway->resolveElementId($calculationId, $paragraphKey);
    if ($legacyElementId === NULL) {
      return;
    }

    $legacyLineId = $this->legacyLineGateway->create($legacyElementId, $actorId);
    $this->legacyMirrorMap->attach($calculationId, $version, $rowId, $legacyLineId);
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


  private function nextSortOrder(int $calculationId, string $version, string $paragraphKey): int {
    $query = $this->database->select('brebo_calculation_row_domain', 'r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('paragraph_key', $paragraphKey);
    $query->addExpression('MAX(sort_order)', 'max_sort_order');
    return ((int) $query->execute()->fetchField()) + 10;
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
