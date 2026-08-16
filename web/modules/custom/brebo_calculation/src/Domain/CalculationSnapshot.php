<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/** Immutable normalized truth of one calculation version. */
final readonly class CalculationSnapshot {

  /**
   * @param list<StructureNode> $structure
   * @param list<CalculationRow> $rows
   */
  public function __construct(
    public int $calculationId,
    public CalculationVersion $version,
    public array $structure,
    public array $rows,
    public CalculationTotals $totals,
    public CommercialResult $commercial,
  ) {
    if ($calculationId <= 0) {
      throw new \InvalidArgumentException('Calculation id must be positive.');
    }
    if (!$version->status->isLocked()) {
      throw new \InvalidArgumentException('Only locked calculation versions may become immutable snapshots.');
    }
  }

  /** Canonical hash payload excludes presentation/output concerns. */
  public function contentHash(): string {
    $payload = [
      'calculation_id' => $this->calculationId,
      'version' => $this->version->version,
      'classification' => $this->version->classificationSystem->value,
      'parameters' => get_object_vars($this->version->parameters),
      'structure' => array_map(static fn (StructureNode $node): array => get_object_vars($node), $this->structure),
      'rows' => array_map(static fn (CalculationRow $row): array => [
        'legacy_line_id' => $row->legacyLineId,
        'paragraph_id' => $row->paragraphId,
        'type' => $row->type->value,
        'description' => $row->description,
        'quantity' => $row->quantity,
        'actual_quantity' => $row->actualQuantity,
        'unit' => $row->unit,
        'unit_costs' => $row->unitCosts->toArray(),
        'sort_order' => $row->sortOrder,
        'location_ref' => $row->locationRef,
        'memo' => $row->memo,
      ], $this->rows),
      'totals' => $this->totals->toArray(),
      'commercial' => $this->commercial->toArray(),
    ];

    return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
  }

}
