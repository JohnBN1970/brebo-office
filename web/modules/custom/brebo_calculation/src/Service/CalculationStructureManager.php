<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/** Creates and reorders calculation structure while preserving legacy identity. */
final class CalculationStructureManager {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function addMainGroup(int $calculationId, string $version, string $code, string $label, AccountInterface $account): string {
    $versionRow = $this->assertEditable($calculationId, $version, $account);
    $code = trim($code);
    $label = trim($label);
    if ($label === '') {
      throw new \InvalidArgumentException('Main group label is required.');
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $sequence = $this->nextLegacySequence($calculationId, 'brebo_calc_component', 'field_brebo_component_sequence');
    $transaction = $this->database->startTransaction();
    try {
      $component = $storage->create([
        'type' => 'brebo_calc_component',
        'title' => $label,
        'status' => 1,
        'uid' => $account->id(),
        'field_brebo_calculation_ref' => ['target_id' => $calculationId],
        'field_brebo_component_code' => $code,
        'field_brebo_component_sequence' => $sequence,
      ]);
      $component->save();
      $nodeKey = 'component_' . $component->id();
      $this->database->insert('brebo_calculation_structure')->fields([
        'calculation_id' => $calculationId,
        'version' => $version,
        'node_key' => $nodeKey,
        'parent_key' => NULL,
        'node_type' => 'main_group',
        'depth' => 0,
        'classification_system' => $versionRow['classification_system'],
        'code' => $code ?: NULL,
        'label' => $label,
        'sort_order' => $sequence,
        'location_ref' => NULL,
      ])->execute();
      return $nodeKey;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  public function addParagraph(int $calculationId, string $version, string $parentKey, string $code, string $label, ?string $locationRef, AccountInterface $account): string {
    $versionRow = $this->assertEditable($calculationId, $version, $account);
    $parent = $this->structureNode($calculationId, $version, $parentKey);
    if ($parent['node_type'] !== 'main_group') {
      throw new \InvalidArgumentException('Paragraphs must currently be attached to a main group.');
    }
    if (!preg_match('/^component_(\d+)$/', $parentKey, $matches)) {
      throw new \RuntimeException('Main group has no legacy component identity.');
    }

    $componentId = (int) $matches[1];
    $code = trim($code);
    $label = trim($label);
    if ($label === '') {
      throw new \InvalidArgumentException('Paragraph label is required.');
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $sequence = $this->nextLegacySequence($calculationId, 'brebo_calc_element', 'field_brebo_element_sequence');
    $transaction = $this->database->startTransaction();
    try {
      $element = $storage->create([
        'type' => 'brebo_calc_element',
        'title' => $label,
        'status' => 1,
        'uid' => $account->id(),
        'field_brebo_calculation_ref' => ['target_id' => $calculationId],
        'field_brebo_calc_component_ref' => ['target_id' => $componentId],
        'field_brebo_element_code' => $code,
        'field_brebo_element_sequence' => $sequence,
        'field_brebo_element_scope' => $label,
        'field_brebo_recipe_quantity' => '1.0000',
        'field_brebo_recipe_unit' => 'post',
      ]);
      $element->save();
      $nodeKey = 'element_' . $element->id();
      $this->database->insert('brebo_calculation_structure')->fields([
        'calculation_id' => $calculationId,
        'version' => $version,
        'node_key' => $nodeKey,
        'parent_key' => $parentKey,
        'node_type' => 'paragraph',
        'depth' => 1,
        'classification_system' => $versionRow['classification_system'],
        'code' => $code ?: NULL,
        'label' => $label,
        'sort_order' => $sequence,
        'location_ref' => $locationRef ?: NULL,
      ])->execute();
      return $nodeKey;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  public function reorder(int $calculationId, string $version, string $nodeKey, int $sortOrder, AccountInterface $account): void {
    $this->assertEditable($calculationId, $version, $account);
    $node = $this->structureNode($calculationId, $version, $nodeKey);
    $transaction = $this->database->startTransaction();
    try {
      $this->database->update('brebo_calculation_structure')
        ->fields(['sort_order' => $sortOrder])
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->condition('node_key', $nodeKey)
        ->execute();

      if ($node['node_type'] === 'main_group' && preg_match('/^component_(\d+)$/', $nodeKey, $matches)) {
        $entity = $this->entityTypeManager->getStorage('node')->load((int) $matches[1]);
        if ($entity instanceof NodeInterface && $entity->hasField('field_brebo_component_sequence')) {
          $entity->set('field_brebo_component_sequence', $sortOrder);
          $entity->save();
        }
      }
      if ($node['node_type'] === 'paragraph' && preg_match('/^element_(\d+)$/', $nodeKey, $matches)) {
        $entity = $this->entityTypeManager->getStorage('node')->load((int) $matches[1]);
        if ($entity instanceof NodeInterface && $entity->hasField('field_brebo_element_sequence')) {
          $entity->set('field_brebo_element_sequence', $sortOrder);
          $entity->save();
        }
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /** @return array<string,mixed> */
  private function assertEditable(int $calculationId, string $version, AccountInterface $account): array {
    if (!$account->hasPermission('edit brebo calculation workbench')) {
      throw new \RuntimeException('Missing calculation workbench edit permission.');
    }
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    if (!$row || $row['locked_at'] !== NULL || $row['status'] !== 'draft') {
      throw new \RuntimeException('Only unlocked draft calculation versions may be changed.');
    }
    $calculation = $this->entityTypeManager->getStorage('node')->load($calculationId);
    if (!$calculation instanceof NodeInterface || !$calculation->access('update', $account)) {
      throw new \RuntimeException('Calculation update access denied.');
    }
    return $row;
  }

  /** @return array<string,mixed> */
  private function structureNode(int $calculationId, string $version, string $nodeKey): array {
    $node = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('node_key', $nodeKey)
      ->execute()->fetchAssoc();
    if (!$node) {
      throw new \InvalidArgumentException('Calculation structure node not found.');
    }
    return $node;
  }

  private function nextLegacySequence(int $calculationId, string $bundle, string $sequenceField): int {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE)->condition('type', $bundle);
    if ($bundle === 'brebo_calc_component' || $bundle === 'brebo_calc_element') {
      $query->condition('field_brebo_calculation_ref.target_id', $calculationId);
    }
    $ids = $query->sort($sequenceField, 'DESC')->range(0, 1)->execute();
    $last = $ids ? $storage->load(reset($ids)) : NULL;
    return $last instanceof NodeInterface ? ((int) $last->get($sequenceField)->value + 10) : 10;
  }

}
