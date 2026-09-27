<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;

/** Creates and reorders calculation structure while preserving legacy identity. */
final class CalculationStructureManager {

  public function __construct(
    private readonly Connection $database,
    private readonly CalculationAccessGatewayInterface $accessGateway,
    private readonly CalculationStructureIdentityGenerator $structureIdentityGenerator,
  ) {}

  public function addMainGroup(int $calculationId, string $version, string $code, string $label, int $actorId): string {
    $versionRow = $this->assertEditable($calculationId, $version, $actorId);
    $code = trim($code);
    $label = trim($label);
    if ($label === '') {
      throw new \InvalidArgumentException('Main group label is required.');
    }

    $transaction = $this->database->startTransaction();
    try {
      $sequence = $this->nextSortOrder($calculationId, $version, NULL);
      $nodeKey = $this->structureIdentityGenerator->mainGroupKey($calculationId, $version);
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

  public function addParagraph(int $calculationId, string $version, string $parentKey, string $code, string $label, ?string $locationRef, int $actorId): string {
    $versionRow = $this->assertEditable($calculationId, $version, $actorId);
    $parent = $this->structureNode($calculationId, $version, $parentKey);
    if ($parent['node_type'] !== 'main_group') {
      throw new \InvalidArgumentException('Paragraphs must currently be attached to a main group.');
    }
    $code = trim($code);
    $label = trim($label);
    if ($label === '') {
      throw new \InvalidArgumentException('Paragraph label is required.');
    }

    $transaction = $this->database->startTransaction();
    try {
      $sequence = $this->nextSortOrder($calculationId, $version, $parentKey);
      $nodeKey = $this->structureIdentityGenerator->paragraphKey($calculationId, $version);
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

  public function reorder(int $calculationId, string $version, string $nodeKey, int $sortOrder, int $actorId): void {
    $this->assertEditable($calculationId, $version, $actorId);
    $node = $this->structureNode($calculationId, $version, $nodeKey);
    $transaction = $this->database->startTransaction();
    try {
      $this->database->update('brebo_calculation_structure')
        ->fields(['sort_order' => $sortOrder])
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->condition('node_key', $nodeKey)
        ->execute();

    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /** @return array<string,mixed> */
  private function assertEditable(int $calculationId, string $version, int $actorId): array {
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    if (!$row || $row['locked_at'] !== NULL || $row['status'] !== 'draft') {
      throw new \RuntimeException('Only unlocked draft calculation versions may be changed.');
    }
    $this->accessGateway->assertCanEditWorkbench($calculationId, $actorId);
    return $row;
  }

  private function nextSortOrder(int $calculationId, string $version, ?string $parentKey): int {
    $query = $this->database->select('brebo_calculation_structure', 's')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version);
    if ($parentKey === NULL) {
      $query->isNull('parent_key');
    }
    else {
      $query->condition('parent_key', $parentKey);
    }
    $query->addExpression('MAX(sort_order)', 'max_sort_order');
    return ((int) $query->execute()->fetchField()) + 10;
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


}
