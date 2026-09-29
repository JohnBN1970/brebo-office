<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationStructureRepositoryInterface;
use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;

/** Creates and reorders calculation structure while preserving legacy identity. */
final class CalculationStructureManager {

  public function __construct(
    private readonly CalculationStructureRepositoryInterface $repository,
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

    $sequence = $this->repository->nextSortOrder($calculationId, $version, NULL);
      $nodeKey = $this->structureIdentityGenerator->mainGroupKey($calculationId, $version);
      $this->repository->insert([
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
      ]);
    return $nodeKey;
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

    $sequence = $this->repository->nextSortOrder($calculationId, $version, $parentKey);
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

  public function reorder(int $calculationId, string $version, string $nodeKey, int $sortOrder, int $actorId): void {
    $this->assertEditable($calculationId, $version, $actorId);
    $node = $this->structureNode($calculationId, $version, $nodeKey);
    $this->repository->reorder($calculationId, $version, $nodeKey, $sortOrder);
  }

  /** @return array<string,mixed> */
  private function assertEditable(int $calculationId, string $version, int $actorId): array {
    $row = $this->repository->versionState($calculationId, $version);
    if (!$row || $row['locked_at'] !== NULL || $row['status'] !== 'draft') {
      throw new \RuntimeException('Only unlocked draft calculation versions may be changed.');
    }
    $this->accessGateway->assertCanEditWorkbench($calculationId, $actorId);
    return $row;
  }

  /** @return array<string,mixed> */
  private function structureNode(int $calculationId, string $version, string $nodeKey): array {
    $node = $this->repository->node($calculationId, $version, $nodeKey);
    if (!$node) {
      throw new \InvalidArgumentException('Calculation structure node not found.');
    }
    return $node;
  }


}
