<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;
use Drupal\brebo_calculation\Contract\CalculationLegacyLineCompatibilityInterface;
use Drupal\brebo_calculation\Contract\CalculationRowRepositoryInterface;

/** Guarded mutations for editable calculation rows. */
final class CalculationRowManager {

  public function __construct(
    private readonly CalculationRowRepositoryInterface $repository,
    private readonly CalculationLegacyLineCompatibilityInterface $legacyCompatibility,
    private readonly CalculationAccessGatewayInterface $accessGateway,
    private readonly CalculationRowIdentityGenerator $rowIdentityGenerator,
  ) {}

  public function add(int $calculationId, string $version, string $paragraphKey, int $actorId): int {
    $this->assertEditable($calculationId, $version, $actorId);
    $this->assertLeafParagraph($calculationId, $version, $paragraphKey);

    $rowId = $this->rowIdentityGenerator->next();
    $this->repository->insert([
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
      'sort_order' => $this->repository->nextSortOrder($calculationId, $version, $paragraphKey),
      'labour_unit_cost' => 0,
      'material_unit_cost' => 0,
      'equipment_unit_cost' => 0,
      'subcontracting_unit_cost' => 0,
      'other_unit_cost' => 0,
    ]);

    $this->legacyCompatibility->createForRow($calculationId, $version, $rowId, $paragraphKey, $actorId);
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

    $this->repository->update($calculationId, $version, $rowId, $costs + [
        'description' => $description,
        'contract_quantity' => $quantity,
        'unit' => $unit,
      ]);

    $this->legacyCompatibility->updateQuickEntry(
      $calculationId,
      $version,
      $rowId,
      $description,
      $unit,
      $quantity,
      $costs,
    );
  }

  public function duplicate(int $calculationId, string $version, int $rowId, int $actorId): int {
    $this->assertEditable($calculationId, $version, $actorId);
    $domain = $this->domainRow($calculationId, $version, $rowId);

    unset($domain['row_id'], $domain['calculation_id'], $domain['version']);
    $copyRowId = $this->rowIdentityGenerator->next();
    $domain['row_id'] = $copyRowId;
    $domain['sort_order'] = $this->repository->nextSortOrder($calculationId, $version, (string) $domain['paragraph_key']);
    $domain['calculation_id'] = $calculationId;
    $domain['version'] = $version;
    $this->repository->insert($domain);

    $this->legacyCompatibility->duplicateForRow(
      $calculationId,
      $version,
      $rowId,
      $copyRowId,
      (string) $domain['paragraph_key'],
      $actorId,
    );

    return $copyRowId;
  }

  public function delete(int $calculationId, string $version, int $rowId, int $actorId): void {
    $this->assertEditable($calculationId, $version, $actorId);
    $domain = $this->domainRow($calculationId, $version, $rowId);
    $this->repository->delete($calculationId, $version, $rowId);

    $this->legacyCompatibility->deleteForRow($calculationId, $version, $rowId);
  }

  public function move(int $calculationId, string $version, int $rowId, string $targetParagraphKey, int $actorId): void {
    $this->assertEditable($calculationId, $version, $actorId);
    $domain = $this->domainRow($calculationId, $version, $rowId);
    $this->assertLeafParagraph($calculationId, $version, $targetParagraphKey);

    $this->repository->update($calculationId, $version, $rowId, [
      'paragraph_key' => $targetParagraphKey,
      'sort_order' => $this->repository->nextSortOrder($calculationId, $version, $targetParagraphKey),
    ]);

    $this->legacyCompatibility->moveRow($calculationId, $version, $rowId, $targetParagraphKey);
  }

  /** @return array<string,mixed> */
  private function domainRow(int $calculationId, string $version, int $rowId): array {
    $row = $this->repository->row($calculationId, $version, $rowId);
    if (!$row) {
      throw new \InvalidArgumentException('Row does not belong to this calculation version.');
    }
    return $row;
  }

  private function assertEditable(int $calculationId, string $version, int $actorId): void {
    $row = $this->repository->versionState($calculationId, $version);
    if (!$row || $row['locked_at'] !== NULL || $row['status'] !== 'draft') {
      throw new \RuntimeException('Only unlocked draft calculation versions may be changed.');
    }
    $this->accessGateway->assertCanEditWorkbench($calculationId, $actorId);
  }

  private function assertLeafParagraph(int $calculationId, string $version, string $paragraphKey): void {
    $node = $this->repository->structureNode($calculationId, $version, $paragraphKey);
    if (!$node || $node['node_type'] !== 'paragraph') {
      throw new \InvalidArgumentException('Rows can only be attached to paragraphs.');
    }
    $children = $this->repository->structureChildCount($calculationId, $version, $paragraphKey);
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
