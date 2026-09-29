<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationStructureLegacyGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Transitional Drupal adapter for legacy component/element identities.
 */
final class DrupalCalculationStructureLegacyGateway implements CalculationStructureLegacyGatewayInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function createMainGroup(int $calculationId, string $code, string $label, int $actorId): array {
    $sequence = $this->nextSequence($calculationId, 'brebo_calc_component', 'field_brebo_component_sequence');
    $component = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'brebo_calc_component',
      'title' => $label,
      'status' => 1,
      'uid' => $actorId,
      'field_brebo_calculation_ref' => ['target_id' => $calculationId],
      'field_brebo_component_code' => $code,
      'field_brebo_component_sequence' => $sequence,
    ]);
    $component->save();
    return ['id' => (int) $component->id(), 'sequence' => $sequence];
  }

  public function createParagraph(int $calculationId, int $componentId, string $code, string $label, int $actorId): array {
    $sequence = $this->nextSequence($calculationId, 'brebo_calc_element', 'field_brebo_element_sequence');
    $element = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'brebo_calc_element',
      'title' => $label,
      'status' => 1,
      'uid' => $actorId,
      'field_brebo_calculation_ref' => ['target_id' => $calculationId],
      'field_brebo_calc_component_ref' => ['target_id' => $componentId],
      'field_brebo_element_code' => $code,
      'field_brebo_element_sequence' => $sequence,
      'field_brebo_element_scope' => $label,
      'field_brebo_recipe_quantity' => '1.0000',
      'field_brebo_recipe_unit' => 'post',
    ]);
    $element->save();
    return ['id' => (int) $element->id(), 'sequence' => $sequence];
  }

  public function reorder(string $nodeType, int $legacyId, int $sortOrder): void {
    $field = $nodeType === 'main_group' ? 'field_brebo_component_sequence' : 'field_brebo_element_sequence';
    $entity = $this->entityTypeManager->getStorage('node')->load($legacyId);
    if ($entity instanceof NodeInterface && $entity->hasField($field)) {
      $entity->set($field, $sortOrder);
      $entity->save();
    }
  }

  private function nextSequence(int $calculationId, string $bundle, string $field): int {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->condition('field_brebo_calculation_ref.target_id', $calculationId)
      ->sort($field, 'DESC')->range(0, 1)->execute();
    $last = $ids ? $storage->load(reset($ids)) : NULL;
    return $last instanceof NodeInterface ? ((int) $last->get($field)->value + 10) : 10;
  }

}
