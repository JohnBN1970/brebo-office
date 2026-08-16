<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Domain\CalculationRow;
use Drupal\brebo_calculation\Domain\ClassificationSystem;
use Drupal\brebo_calculation\Domain\LegacyDryRunResult;
use Drupal\brebo_calculation\Domain\StructureNode;
use Drupal\brebo_calculation\Domain\StructureNodeType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Builds the new domain model from legacy nodes without writing anything. */
final class LegacyDryRunService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LegacyRuleMapper $ruleMapper,
    private readonly LegacyCostMapper $costMapper,
    private readonly CalculationTotalizer $totalizer,
    private readonly LegacyReconciler $reconciler,
  ) {}

  public function preview(int $calculationId): LegacyDryRunResult {
    $storage = $this->entityTypeManager->getStorage('node');
    $calculation = $storage->load($calculationId);
    if (!$calculation instanceof NodeInterface || $calculation->bundle() !== 'brebo_calculation') {
      throw new \InvalidArgumentException('Legacy calculation not found.');
    }

    $componentIds = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'brebo_calc_component')
      ->condition('field_brebo_calculation_ref.target_id', $calculationId)
      ->sort('field_brebo_component_sequence')->execute();
    $elementIds = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'brebo_calc_element')
      ->condition('field_brebo_calculation_ref.target_id', $calculationId)
      ->sort('field_brebo_element_sequence')->execute();
    $components = $storage->loadMultiple($componentIds);
    $elements = $storage->loadMultiple($elementIds);

    $structure = [];
    foreach ($components as $component) {
      if (!$component instanceof NodeInterface) {
        continue;
      }
      $structure[] = new StructureNode(
        id: 'component_' . $component->id(),
        type: StructureNodeType::MainGroup,
        code: (string) ($component->get('field_brebo_component_code')->value ?? ''),
        label: $component->label(),
        depth: 0,
        sortOrder: (int) ($component->get('field_brebo_component_sequence')->value ?? 0),
      );
    }

    foreach ($elements as $element) {
      if (!$element instanceof NodeInterface) {
        continue;
      }
      $componentId = (int) ($element->get('field_brebo_calc_component_ref')->target_id ?? 0);
      $zoneId = $element->hasField('field_brebo_technical_zone_ref')
        ? (int) ($element->get('field_brebo_technical_zone_ref')->target_id ?? 0) : 0;
      $structure[] = new StructureNode(
        id: 'element_' . $element->id(),
        type: StructureNodeType::Paragraph,
        code: (string) ($element->get('field_brebo_element_code')->value ?? ''),
        label: $element->label(),
        depth: 1,
        sortOrder: (int) ($element->get('field_brebo_element_sequence')->value ?? 0),
        parentId: 'component_' . $componentId,
        locationRef: $zoneId > 0 ? 'building_zone:' . $zoneId : NULL,
      );
    }

    $lineIds = $elementIds ? $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'brebo_calc_line')
      ->condition('field_brebo_calc_element_ref.target_id', array_values($elementIds), 'IN')
      ->execute() : [];
    $lines = $storage->loadMultiple($lineIds);
    $rows = [];
    $warnings = [];
    $legacyAmount = 0.0;

    foreach ($lines as $line) {
      if (!$line instanceof NodeInterface) {
        continue;
      }
      $lineType = (string) ($line->get('field_brebo_line_type')->value ?? 'Calculatieregel');
      $postType = (string) ($line->get('field_brebo_line_post_type')->value ?? 'Vaste post');
      $category = (string) ($line->get('field_brebo_cost_category')->value ?? 'Overig');
      $quantity = (float) ($line->get('field_brebo_contract_quantity')->value ?? 0);
      $actualRaw = $line->get('field_brebo_actual_quantity')->value;
      $actual = ($actualRaw === NULL || $actualRaw === '') ? NULL : (float) $actualRaw;
      $unitPrice = (float) ($line->get('field_brebo_unit_price')->value ?? 0);
      $rule = $this->ruleMapper->map($lineType, $postType);
      $cost = $this->costMapper->map($category, $unitPrice);
      foreach ([$rule['warning'], $cost['warning']] as $warning) {
        if ($warning !== NULL) {
          $warnings[] = 'Regel ' . $line->id() . ': ' . $warning;
        }
      }

      $elementId = (int) ($line->get('field_brebo_calc_element_ref')->target_id ?? 0);
      $rows[] = new CalculationRow(
        legacyLineId: (int) $line->id(),
        paragraphId: 'element_' . $elementId,
        type: $rule['type'],
        description: (string) ($line->get('field_brebo_line_description')->value ?? $line->label()),
        quantity: $quantity,
        unit: (string) ($line->get('field_brebo_unit')->value ?? ''),
        unitCosts: $cost['costs'],
        sortOrder: (int) ($line->get('field_brebo_line_sequence')->value ?? 0),
        actualQuantity: $actual,
      );

      if ($lineType !== 'Notitie') {
        $legacyAmount += $quantity * $unitPrice;
      }
    }

    $totals = $this->totalizer->total($rows);
    $reconciliation = $this->reconciler->compare($legacyAmount, $totals->includingOptions());

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
