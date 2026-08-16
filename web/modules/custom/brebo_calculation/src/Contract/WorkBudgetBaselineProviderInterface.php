<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Exposes an execution-safe baseline derived from a fixed calculation version.
 */
interface WorkBudgetBaselineProviderInterface {

  /**
   * Builds the normalized baseline consumed by project/work-budget logic.
   *
   * Commercial margin and sales presentation are deliberately excluded.
   */
  public function baseline(int $calculationId, string $version): object;

}
