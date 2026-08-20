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
   * @param array<int,array{type:string,id:int,paragraph?:string}> $blocks
   */
  public function apply(int $calculationId, string $version, string $paragraphKey, array $blocks, AccountInterface $account): void {
    $this->assertEditable($calculationId, $version, $account);
    if ($paragraphKey === '__workspace__') {
      $this->applyWorkspace($calculationId, $version, $blocks);
      return;
    }

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

    $submittedKeys = array_keys($submitted);
    $expectedKeys = array_keys($expected);
    sort($submittedKeys);
    sort($expectedKeys);
    if ($submittedKeys !== $expectedKeys) {
      throw new \RuntimeException('Block order must contain every current row and recipe exactly once.');
    }

    $transaction = $this->database->startTransaction();
    try {
      $this->writeParagraphOrder($calculationId, $version, $paragraphKey, $blocks);
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Persists the complete workspace in one transaction, including paragraph moves.
   *
   * @param array<int,array{type:string,id:int,paragraph?:string}> $blocks
   */
  private function applyWorkspace(int $calculationId, string $version, array $blocks): void {
    $expected = $this->expectedWorkspaceBlocks($calculationId, $version);
    $submitted = [];
    $byParagraph = [];

    foreach ($blocks as $block) {
      $type = (string) ($block['type'] ?? '');
      $id = (int) ($block['id'] ?? 0);
      $paragraph = trim((string) ($block['paragraph'] ?? ''));
      if (!in_array($type, ['row', 'recipe'], TRUE) || $id <= 0 || $paragraph === '') {
        throw new \InvalidArgumentException('Invalid calculation workspace order payload.');
      }
      $key = $type . ':' . $id;
      if (isset($submitted[$key])) {
        throw new \InvalidArgumentException('Duplicate calculation block in workspace order payload.');
      }
      $this->assertLeafParagraph($calculationId, $version, $paragraph);
      $submitted[$key] = ['type' => $type, 'id' => $id, 'paragraph' => $paragraph];
      $byParagraph[$paragraph][] = ['type' => $type, 'id' => $id];
    }

    $submittedKeys = array_keys($submitted);
    $expectedKeys = array_keys($expected);
    sort($submittedKeys);
    sort($expectedKeys);
    if ($submittedKeys !== $expectedKeys) {
      throw new \RuntimeException('Workspace order must contain every current row and recipe exactly once.');
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $transaction = $this->database->startTransaction();
    try {
      foreach ($submitted as $block) {
        $type = $block['type'];
        $id = $block['id'];
        $targetParagraph = $block['paragraph'];
        $currentParagraph = (string) $expected[$type . ':' . $id]['paragraph'];
        if ($targetParagraph === $currentParagraph) {
          continue;
        }

        if ($type === 'row') {
          $targetElementId = $this->resolveLegacyElementId($calculationId, $targetParagraph);
          if ($targetElementId === NULL) {
            throw new \RuntimeException('Target paragraph has no safe legacy element mapping.');
          }
          $line = $storage->load($id);
          if (!$line instanceof NodeInterface || $line->bundle() !== 'brebo_calc_line') {
            throw new \RuntimeException('Calculation row no longer exists.');
          }
          $line->set('field_brebo_calc_element_ref', ['target_id' => $targetElementId]);
          $line->setNewRevision(TRUE);
          $line->setRevisionLogMessage('Calculatieregel via calculatiewerkbank naar andere paragraaf verplaatst.');
          $line->save();
          $updated = $this->database->update('brebo_calculation_row_domain')
            ->fields(['paragraph_key' => $targetParagraph])
            ->condition('calc_line_id', $id)
            ->condition('calculation_id', $calculationId)
            ->condition('version', $version)
            ->execute();
        }
        else {
          $updated = $this->database->update('brebo_calculation_recipe_instance')
            ->fields(['paragraph_key' => $targetParagraph])
            ->condition('id', $id)
            ->condition('calculation_id', $calculationId)
            ->condition('calculation_version', $version)
            ->execute();
        }
        if ($updated !== 1) {
          throw new \RuntimeException('Calculation block could not be moved to the target paragraph.');
        }
      }

      foreach ($byParagraph as $paragraph => $paragraphBlocks) {
        $this->writeParagraphOrder($calculationId, $version, $paragraph, $paragraphBlocks);
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /** @param array<int,array{type:string,id:int}> $blocks */
  private function writeParagraphOrder(int $calculationId, string $version, string $paragraphKey, array $blocks): void {
    $storage = $this->entityTypeManager->getStorage('node');
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

  /** @return array<string,array{type:string,id:int,paragraph:string}> */
  private function expectedWorkspaceBlocks(int $calculationId, string $version): array {
    $expected = [];
    $rows = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r', ['calc_line_id', 'paragraph_key'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute();
    foreach ($rows as $row) {
      $id = (int) $row->calc_line_id;
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

  private function assertLeafParagraph(int $calculationId, string $version, string $paragraphKey): void {
    $node = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s', ['node_type'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('node_key', $paragraphKey)
      ->execute()->fetchAssoc();
    if (!$node || $node['node_type'] !== 'paragraph') {
      throw new \InvalidArgumentException('Calculation blocks can only be attached to paragraphs.');
    }
    $children = (int) $this->database->select('brebo_calculation_structure', 's')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('parent_key', $paragraphKey)
      ->countQuery()->execute()->fetchField();
    if ($children > 0) {
      throw new \RuntimeException('Only leaf paragraphs may contain calculation blocks.');
    }
  }

  private function resolveLegacyElementId(int $calculationId, string $paragraphKey): ?int {
    if (!preg_match('/(?:element|paragraph)[_:-]?(\d+)/i', $paragraphKey, $matches)) {
      return NULL;
    }
    $candidate = (int) $matches[1];
    $element = $this->entityTypeManager->getStorage('node')->load($candidate);
    if ($element instanceof NodeInterface
      && $element->bundle() === 'brebo_calc_element'
      && (int) $element->get('field_brebo_calculation_ref')->target_id === $calculationId) {
      return $candidate;
    }
    return NULL;
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
