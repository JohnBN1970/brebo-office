<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationLegacyLineCompatibilityInterface;
use Drupal\brebo_calculation\Contract\CalculationLegacyLineMirrorMapInterface;
use Drupal\brebo_calculation\Contract\CalculationLegacyLineMirrorPolicyInterface;
use Drupal\brebo_calculation\Contract\CalculationLineLegacyGatewayInterface;

/**
 * Isolates every active legacy calc-line compatibility operation.
 */
final class CalculationLegacyLineCompatibility implements CalculationLegacyLineCompatibilityInterface {

  public function __construct(
    private readonly CalculationLineLegacyGatewayInterface $gateway,
    private readonly CalculationLegacyLineMirrorMapInterface $map,
    private readonly CalculationLegacyLineMirrorPolicyInterface $policy,
  ) {}

  public function createForRow(int $calculationId, string $version, int $rowId, string $paragraphKey, int $actorId): void {
    if (!$this->policy->createLegacyMirrors()) {
      return;
    }
    $elementId = $this->gateway->resolveElementId($calculationId, $paragraphKey);
    if ($elementId === NULL) {
      return;
    }
    $legacyLineId = $this->gateway->create($elementId, $actorId);
    $this->map->attach($calculationId, $version, $rowId, $legacyLineId);
  }

  public function duplicateForRow(int $calculationId, string $version, int $sourceRowId, int $copyRowId, string $paragraphKey, int $actorId): void {
    if (!$this->policy->createLegacyMirrors()) {
      return;
    }
    $sourceLegacyId = $this->map->legacyLineId($calculationId, $version, $sourceRowId);
    if ($sourceLegacyId !== NULL) {
      $copyLegacyId = $this->gateway->duplicate($sourceLegacyId, $actorId);
      $this->map->attach($calculationId, $version, $copyRowId, $copyLegacyId);
      return;
    }
    $this->createForRow($calculationId, $version, $copyRowId, $paragraphKey, $actorId);
  }

  public function updateQuickEntry(int $calculationId, string $version, int $rowId, string $description, string $unit, float $quantity, array $unitCosts): void {
    if (!$this->policy->maintainLegacyMirrors()) {
      return;
    }
    $legacyLineId = $this->map->legacyLineId($calculationId, $version, $rowId);
    if ($legacyLineId !== NULL) {
      $this->gateway->updateQuickEntry($legacyLineId, $description, $unit, $quantity, $unitCosts);
    }
  }

  public function deleteForRow(int $calculationId, string $version, int $rowId): void {
    if (!$this->policy->maintainLegacyMirrors()) {
      return;
    }
    $legacyLineId = $this->map->legacyLineId($calculationId, $version, $rowId);
    if ($legacyLineId !== NULL) {
      $this->gateway->delete($legacyLineId);
    }
  }

  public function moveRow(int $calculationId, string $version, int $rowId, string $targetParagraphKey): void {
    if (!$this->policy->maintainLegacyMirrors()) {
      return;
    }
    $legacyLineId = $this->map->legacyLineId($calculationId, $version, $rowId);
    if ($legacyLineId === NULL) {
      return;
    }
    $elementId = $this->gateway->resolveElementId($calculationId, $targetParagraphKey);
    if ($elementId !== NULL) {
      $this->gateway->move($legacyLineId, $elementId);
    }
  }

  public function reorderRow(int $calculationId, string $version, int $rowId, int $sortOrder): void {
    if (!$this->policy->maintainLegacyMirrors()) {
      return;
    }
    $legacyLineId = $this->map->legacyLineId($calculationId, $version, $rowId);
    if ($legacyLineId !== NULL) {
      $this->gateway->reorder($legacyLineId, $sortOrder);
    }
  }

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
  ): void {
    if (!$this->policy->maintainLegacyMirrors()) {
      return;
    }
    $legacyLineId = $this->map->legacyLineId($calculationId, $version, $rowId);
    if ($legacyLineId !== NULL) {
      $this->gateway->updateObjectDerived(
        $legacyLineId,
        $description,
        $unit,
        $quantity,
        $unitCosts,
        $sourceDomain,
        $sourceReference,
        $sourceChecksum,
        $priceSourceRef,
        $priceReason,
      );
    }
  }

}
