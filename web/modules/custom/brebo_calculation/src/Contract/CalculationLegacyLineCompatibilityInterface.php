<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Optional compatibility bridge from BREBO rows to legacy Drupal calc-line mirrors.
 */
interface CalculationLegacyLineCompatibilityInterface {

  public function createForRow(int $calculationId, string $version, int $rowId, string $paragraphKey, int $actorId): void;

  public function duplicateForRow(int $calculationId, string $version, int $sourceRowId, int $copyRowId, string $paragraphKey, int $actorId): void;

  /** @param array<string,float> $unitCosts */
  public function updateQuickEntry(int $calculationId, string $version, int $rowId, string $description, string $unit, float $quantity, array $unitCosts): void;

  public function deleteForRow(int $calculationId, string $version, int $rowId): void;

  public function moveRow(int $calculationId, string $version, int $rowId, string $targetParagraphKey): void;

  public function reorderRow(int $calculationId, string $version, int $rowId, int $sortOrder): void;

  /** @param array<string,float> $unitCosts */
  public function updateObjectDerived(
    int $calculationId,
    string $version,
    int $rowId,
    string $description,
    string $unit,
    float $quantity,
    array $unitCosts,
    string $sourceDomain,
    string $sourceReference,
    string $sourceChecksum,
    ?string $priceSourceRef,
    ?string $priceReason,
  ): void;

}
