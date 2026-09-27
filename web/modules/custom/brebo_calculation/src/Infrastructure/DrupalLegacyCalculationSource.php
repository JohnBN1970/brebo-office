<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\LegacyCalculationSourceInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Transitional Drupal reader for the old calculation node model.
 */
final class DrupalLegacyCalculationSource implements LegacyCalculationSourceInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function load(int $calculationId): array {
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

    $components = [];
    foreach ($storage->loadMultiple($componentIds) as $component) {
      if (!$component instanceof NodeInterface) {
        continue;
      }
      $components[] = [
        'id' => (int) $component->id(),
        'code' => (string) ($component->get('field_brebo_component_code')->value ?? ''),
        'label' => (string) $component->label(),
        'sequence' => (int) ($component->get('field_brebo_component_sequence')->value ?? 0),
      ];
    }

    $elements = [];
    foreach ($storage->loadMultiple($elementIds) as $element) {
      if (!$element instanceof NodeInterface) {
        continue;
      }
      $elements[] = [
        'id' => (int) $element->id(),
        'component_id' => (int) ($element->get('field_brebo_calc_component_ref')->target_id ?? 0),
        'zone_id' => $element->hasField('field_brebo_technical_zone_ref')
          ? (int) ($element->get('field_brebo_technical_zone_ref')->target_id ?? 0)
          : 0,
        'code' => (string) ($element->get('field_brebo_element_code')->value ?? ''),
        'label' => (string) $element->label(),
        'sequence' => (int) ($element->get('field_brebo_element_sequence')->value ?? 0),
      ];
    }

    $lineIds = $elementIds ? $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'brebo_calc_line')
      ->condition('field_brebo_calc_element_ref.target_id', array_values($elementIds), 'IN')
      ->execute() : [];

    $lines = [];
    foreach ($storage->loadMultiple($lineIds) as $line) {
      if (!$line instanceof NodeInterface) {
        continue;
      }
      $actualRaw = $line->get('field_brebo_actual_quantity')->value;
      $lines[] = [
        'id' => (int) $line->id(),
        'element_id' => (int) ($line->get('field_brebo_calc_element_ref')->target_id ?? 0),
        'line_type' => (string) ($line->get('field_brebo_line_type')->value ?? 'Calculatieregel'),
        'post_type' => (string) ($line->get('field_brebo_line_post_type')->value ?? 'Vaste post'),
        'category' => (string) ($line->get('field_brebo_cost_category')->value ?? 'Overig'),
        'quantity' => (float) ($line->get('field_brebo_contract_quantity')->value ?? 0),
        'actual_quantity' => ($actualRaw === NULL || $actualRaw === '') ? NULL : (float) $actualRaw,
        'unit_price' => (float) ($line->get('field_brebo_unit_price')->value ?? 0),
        'description' => (string) ($line->get('field_brebo_line_description')->value ?? $line->label()),
        'unit' => (string) ($line->get('field_brebo_unit')->value ?? ''),
        'sequence' => (int) ($line->get('field_brebo_line_sequence')->value ?? 0),
      ];
    }

    return ['components' => $components, 'elements' => $elements, 'lines' => $lines];
  }

}
