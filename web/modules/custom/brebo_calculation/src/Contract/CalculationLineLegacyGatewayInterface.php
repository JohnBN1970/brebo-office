<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Compatibility boundary for legacy Drupal calculation-line persistence.
 */
interface CalculationLineLegacyGatewayInterface {

  public function create(int $legacyElementId, int $ownerId): int;

  /** @param array<string,float> $unitCosts */
  public function updateQuickEntry(int $lineId, string $description, string $unit, float $quantity, array $unitCosts): void;

  /** @param array<string,float> $unitCosts */
  public function updateObjectDerived(
    int $lineId,
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

  public function duplicate(int $lineId, int $ownerId): int;

  public function delete(int $lineId): void;

  public function move(int $lineId, int $targetElementId): void;

  public function nextSequence(int $elementId): int;

  public function resolveElementId(int $calculationId, string $paragraphKey): ?int;

}
