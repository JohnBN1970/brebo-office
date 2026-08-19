<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/** Persists one shared order for calculation rows and recipe blocks. */
final class CalculationBlockOrderManager {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * @param array<int,array{type:string,id:int}> $blocks
   */
  public function apply(int $calculationId, string $version, string $paragraphKey, array $blocks, AccountInterface $account): void {
    $this->assertEditable($calculationId, $version, $account);

    $expected = $this->expectedBlocks($calculationId, $version, $paragraphKey);
    $submitted = [];
    foreach ($blocks as $block) {
      $type = (string) ($block['type'] ?? '');
      $id = (int) ($block['id'] ?? 0);
      if (!in_array($type, ['row', 'recipe'], TRUE) || $id <= 0) {
        throw new \InvalidArgumentException('Invalid calculation block order payload.');
      }
      $key = $type . ':' . $id;
      if (isset($submitted[$key])) {
        throw new \InvalidArgumentException('Duplicate calculation block in order payload.');
      }
      $submitted[$key] = ['type' => $type, 'id' => $id];
    }

    if (array_keys($submitted) !== array_keys($expected)) {
      $submittedKeys = array_keys($submitted);
      $expectedKeys = array_keys($expected);
      sort($submittedKeys);
      sort($expectedKeys);
      if ($submittedKeys !== $expectedKeys) {
        throw new \RuntimeException('Block order must contain every current row and recipe exactly once.');
      }
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $transaction = $this->database->startTransaction();
    try {
      $position = 10;
      foreach ($blocks as $block) {
        $type = (string) $block['type'];
        $id = (int) $block['id'];
        if ($type === 'row') {
          $line = $storage->load($id);
          if (!$line instanceof NodeInterface || $line->bundle() !== 'brebo_calc_line') {
            throw new \RuntimeException('Calculation row no longer exists.');
          }
          $line->set('field_brebo_line_sequence', $position);
          $line->setNewRevision(TRUE);
          $line->setRevisionLogMessage('Volgorde gewijzigd vanuit de BREBO calculatiewerkbank.');
          $line->save();
        }
        else {
          $updated = $this->database->update('brebo_calculation_recipe_instance')
            ->fields(['sort_order' => $position])
            ->condition('id', $id)
            ->condition('calculation_id', $calculationId)
            ->condition('calculation_version', $version)
            ->condition('paragraph_key', $paragraphKey)
            ->execute();
          if ($updated !== 1) {
            throw new \RuntimeException('Recipe block no longer belongs to this calculation paragraph.');
          }
        }
        $position += 10;
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /** @return array<string,array{type:string,id:int}> */
  private function expectedBlocks(int $calculationId, string $version, string $paragraphKey): array {
    $expected = [];
    $rowIds = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r', ['calc_line_id'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('paragraph_key', $paragraphKey)
      ->execute()->fetchCol();
    foreach ($rowIds as $id) {
      $expected['row:' . (int) $id] = ['type' => 'row', 'id' => (int) $id];
    }

    $recipeIds = $this->database->select('brebo_calculation_recipe_instance', 'i')
      ->fields('i', ['id'])
      ->condition('calculation_id', $calculationId)
      ->condition('calculation_version', $version)
      ->condition('paragraph_key', $paragraphKey)
      ->execute()->fetchCol();
    foreach ($recipeIds as $id) {
      $expected['recipe:' . (int) $id] = ['type' => 'recipe', 'id' => (int) $id];
    }
    return $expected;
  }

  private function assertEditable(int $calculationId, string $version, AccountInterface $account): void {
    if (!$account->hasPermission('edit brebo calculation workbench')) {
      throw new \RuntimeException('Missing calculation workbench edit permission.');
    }
    $versionRow = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['locked_at', 'status'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    if (!$versionRow || $versionRow['locked_at'] !== NULL || $versionRow['status'] !== 'draft') {
      throw new \RuntimeException('Only unlocked draft calculation versions may be reordered.');
    }
    $calculation = $this->entityTypeManager->getStorage('node')->load($calculationId);
    if (!$calculation instanceof NodeInterface || !$calculation->access('update', $account)) {
      throw new \RuntimeException('Calculation update access denied.');
    }
  }

}
