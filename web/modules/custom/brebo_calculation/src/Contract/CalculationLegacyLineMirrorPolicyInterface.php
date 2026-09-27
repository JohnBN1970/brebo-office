<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/** Controls creation of temporary legacy Drupal calculation-line mirrors. */
interface CalculationLegacyLineMirrorPolicyInterface {

  public function createLegacyMirrors(): bool;

  public function maintainLegacyMirrors(): bool;

}
