<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Maps BREBO row identities to optional legacy Drupal calculation-line mirrors.
 */
interface CalculationLegacyLineMirrorMapInterface {

  public function legacyLineId(int $calculationId, string $version, int $rowId): ?int;

  public function attach(int $calculationId, string $version, int $rowId, int $legacyLineId): void;

}
