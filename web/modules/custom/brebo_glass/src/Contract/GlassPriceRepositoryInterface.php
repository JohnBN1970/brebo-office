<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Contract;

interface GlassPriceRepositoryInterface {

  public function catalogAvailable(): bool;

  /** @return array<string,mixed>|null */
  public function findMaterialPrice(string $productCode, float $quantity, string $date): ?array;

  public function snapshotAvailable(): bool;

  public function snapshotExists(int $calculationLineId): bool;

  /** @param array<string,mixed> $values */
  public function insertSnapshot(array $values): void;

}
