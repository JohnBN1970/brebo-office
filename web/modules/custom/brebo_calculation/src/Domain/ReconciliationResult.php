<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/** Result of comparing legacy and new calculation totals during migration. */
final readonly class ReconciliationResult {

  public function __construct(
    public float $legacyAmount,
    public float $newAmount,
    public float $difference,
    public float $tolerance,
    public bool $matches,
  ) {}

}
