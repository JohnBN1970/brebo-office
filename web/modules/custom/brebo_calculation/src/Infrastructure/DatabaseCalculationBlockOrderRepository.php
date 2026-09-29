<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationBlockOrderRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationBlockOrderRepository implements CalculationBlockOrderRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function transactional(callable $callback): mixed {
    $transaction = $this->database->startTransaction();
    try {
      return $callback();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  public function isEditableVersion(int $calculationId, string $version): bool {
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['locked_at', 'status'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    return (bool) ($row && $row['locked_at'] === NULL && $row['status'] === 'draft');
  }

  public function expectedBlocks(int $calculationId, string $version, string $paragraphKey): array {
    $expected = [];
    $rowIds = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r', ['row_id'])
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

  public function expectedWorkspaceBlocks(int $calculationId, string $version): array {
    $expected = [];
    $rows = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r', ['row_id', 'paragraph_key'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute();
    foreach ($rows as $row) {
      $id = (int) $row->row_id;
      $expected['row:' . $id] = ['type' => 'row', 'id' => $id, 'paragraph' => (string) $row->paragraph_key];
    }

    $recipes = $this->database->select('brebo_calculation_recipe_instance', 'i')
      ->fields('i', ['id', 'paragraph_key'])
      ->condition('calculation_id', $calculationId)
      ->condition('calculation_version', $version)
      ->execute();
    foreach ($recipes as $recipe) {
      $id = (int) $recipe->id;
      $expected['recipe:' . $id] = ['type' => 'recipe', 'id' => $id, 'paragraph' => (string) $recipe->paragraph_key];
    }
    return $expected;
  }

  public function isLeafParagraph(int $calculationId, string $version, string $paragraphKey): bool {
    $node = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s', ['node_type'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('node_key', $paragraphKey)
      ->execute()->fetchAssoc();
    if (!$node || $node['node_type'] !== 'paragraph') {
      return FALSE;
    }
    $children = (int) $this->database->select('brebo_calculation_structure', 's')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('parent_key', $paragraphKey)
      ->countQuery()->execute()->fetchField();
    return $children === 0;
  }

  public function moveRow(int $calculationId, string $version, int $rowId, string $paragraphKey): bool {
    return $this->database->update('brebo_calculation_row_domain')
      ->fields(['paragraph_key' => $paragraphKey])
      ->condition('row_id', $rowId)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute() === 1;
  }

  public function moveRecipe(int $calculationId, string $version, int $recipeId, string $paragraphKey): bool {
    return $this->database->update('brebo_calculation_recipe_instance')
      ->fields(['paragraph_key' => $paragraphKey])
      ->condition('id', $recipeId)
      ->condition('calculation_id', $calculationId)
      ->condition('calculation_version', $version)
      ->execute() === 1;
  }

  public function reorderRow(int $calculationId, string $version, int $rowId, string $paragraphKey, int $sortOrder): bool {
    return $this->database->update('brebo_calculation_row_domain')
      ->fields(['sort_order' => $sortOrder])
      ->condition('row_id', $rowId)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('paragraph_key', $paragraphKey)
      ->execute() === 1;
  }

  public function reorderRecipe(int $calculationId, string $version, int $recipeId, string $paragraphKey, int $sortOrder): bool {
    return $this->database->update('brebo_calculation_recipe_instance')
      ->fields(['sort_order' => $sortOrder])
      ->condition('id', $recipeId)
      ->condition('calculation_id', $calculationId)
      ->condition('calculation_version', $version)
      ->condition('paragraph_key', $paragraphKey)
      ->execute() === 1;
  }
}
