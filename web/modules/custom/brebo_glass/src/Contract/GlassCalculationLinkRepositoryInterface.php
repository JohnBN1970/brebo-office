<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Contract;

interface GlassCalculationLinkRepositoryInterface {

  public function isAvailable(): bool;

  public function countLinks(int $positionId, int $calculationId, string $version): int;

  /** @return array<int,array{row_id:int,source_checksum:string}> */
  public function links(int $positionId, int $calculationId, string $version): array;

}
