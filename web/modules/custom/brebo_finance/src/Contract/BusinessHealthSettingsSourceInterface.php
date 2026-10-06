<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface BusinessHealthSettingsSourceInterface {

  /** @return array<string,mixed>|null */
  public function fixedCostCategories(): ?array;

  /** @return array{red:float,orange:float} */
  public function liquidityThresholds(): array;

}
