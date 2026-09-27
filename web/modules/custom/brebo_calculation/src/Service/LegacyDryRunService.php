<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Domain\CalculationRow;
use Drupal\brebo_calculation\Domain\ClassificationSystem;
use Drupal\brebo_calculation\Domain\LegacyDryRunResult;
use Drupal\brebo_calculation\Domain\StructureNode;
use Drupal\brebo_calculation\Domain\StructureNodeType;
use Drupal\brebo_calculation\Contract\LegacyCalculationSourceInterface;

/** Builds the new domain model from legacy nodes without writing anything. */
final class LegacyDryRunService {

  public function __construct(
    private readonly LegacyCalculationSourceInterface $legacySource,
    private readonly LegacyRuleMapper $ruleMapper,
    private readonly LegacyCostMapper $costMapper,
    private readonly CalculationTotalizer $totalizer,
    private readonly LegacyReconciler $reconciler,
  ) {}

  public function preview(int $calculationId): LegacyDryRunResult {
    $legacy = $this->legacySource->load($calculationId);

    $structure = [];
    foreach ($legacy['components'] as $component) {
      $structure[] = new StructureNode(
        id: 'component_' . $component['id'],
        type: StructureNodeType::MainGroup,
        code: $component['code'],
        label: $component['label'],
        depth: 0,
        sortOrder: $component['sequence'],
      );
    }

    foreach ($legacy['elements'] as $element) {
      $structure[] = new StructureNode(
        id: 'element_' . $element['id'],
        type: StructureNodeType::Paragraph,
        code: $element['code'],
        label: $element['label'],
        depth: 1,
        sortOrder: $element['sequence'],
        parentId: 'component_' . $element['component_id'],
        locationRef: $element['zone_id'] > 0 ? 'building_zone:' . $element['zone_id'] : NULL,
      );
    }

    $rows = [];
    $warnings = [];
    $legacyAmount = 0.0;

    foreach ($legacy['lines'] as $line) {
      $rule = $this->ruleMapper->map($line['line_type'], $line['post_type']);
      $cost = $this->costMapper->map($line['category'], $line['unit_price']);
      foreach ([$rule['warning'], $cost['warning']] as $warning) {
        if ($warning !== NULL) {
          $warnings[] = 'Regel ' . $line['id'] . ': ' . $warning;
        }
      }

      $rows[] = new CalculationRow(
        legacyLineId: $line['id'],
        paragraphId: 'element_' . $line['element_id'],
        type: $rule['type'],
        description: $line['description'],
        quantity: $line['quantity'],
        unit: $line['unit'],
        unitCosts: $cost['costs'],
        sortOrder: $line['sequence'],
        actualQuantity: $line['actual_quantity'],
      );

      if ($line['line_type'] !== 'Notitie') {
        $legacyAmount += $line['quantity'] * $line['unit_price'];
      }
    }

    $totals = $this->totalizer->total($rows);

    $contractRows = array_map(static function (CalculationRow $row): CalculationRow {
      if ($row->actualQuantity === NULL) {
        return $row;
      }
      return new CalculationRow(
        legacyLineId: $row->legacyLineId,
        paragraphId: $row->paragraphId,
        type: $row->type,
        description: $row->description,
        quantity: $row->quantity,
        unit: $row->unit,
        unitCosts: $row->unitCosts,
        sortOrder: $row->sortOrder,
        locationRef: $row->locationRef,
        actualQuantity: NULL,
        memo: $row->memo,
      );
    }, $rows);
    $contractTotals = $this->totalizer->total($contractRows);
    $reconciliation = $this->reconciler->compare($legacyAmount, $contractTotals->includingOptions());

    return new LegacyDryRunResult(
      calculationId: $calculationId,
      structure: $structure,
      rows: $rows,
      totals: $totals,
      reconciliation: $reconciliation,
      warnings: array_values(array_unique($warnings)),
    );
  }
}
