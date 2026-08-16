<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/** Read-only migration preview for one legacy calculation. */
final readonly class LegacyDryRunResult {

  /**
   * @param list<StructureNode> $structure
   * @param list<CalculationRow> $rows
   * @param list<string> $warnings
   */
  public function __construct(
    public int $calculationId,
    public array $structure,
    public array $rows,
    public CalculationTotals $totals,
    public ReconciliationResult $reconciliation,
    public array $warnings = [],
  ) {}

  public function isSafeToMigrate(): bool {
    return $this->reconciliation->matches && $this->warnings === [];
  }

}
