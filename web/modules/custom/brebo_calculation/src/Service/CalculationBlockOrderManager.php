<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;
use Drupal\brebo_calculation\Contract\CalculationBlockOrderRepositoryInterface;
use Drupal\brebo_calculation\Contract\CalculationLegacyLineCompatibilityInterface;

/** Persists one shared order for calculation rows and recipe blocks. */
final class CalculationBlockOrderManager {

  public function __construct(
    private readonly CalculationBlockOrderRepositoryInterface $repository,
    private readonly CalculationLegacyLineCompatibilityInterface $legacyCompatibility,
    private readonly CalculationAccessGatewayInterface $accessGateway,
  ) {}

  /**
   * @param array<int,array{type:string,id:int,paragraph?:string}> $blocks
   */
  public function apply(int $calculationId, string $version, string $paragraphKey, array $blocks, int $actorId): void {
    $this->assertEditable($calculationId, $version, $actorId);
    if ($paragraphKey === '__workspace__') {
      $this->applyWorkspace($calculationId, $version, $blocks);
      return;
    }

    $expected = $this->repository->expectedBlocks($calculationId, $version, $paragraphKey);
    $submitted = $this->normalizeSubmitted($blocks, FALSE);

    $submittedKeys = array_keys($submitted);
    $expectedKeys = array_keys($expected);
    sort($submittedKeys);
    sort($expectedKeys);
    if ($submittedKeys !== $expectedKeys) {
      throw new \RuntimeException('Block order must contain every current row and recipe exactly once.');
    }

    $this->repository->transactional(function () use ($calculationId, $version, $paragraphKey, $blocks): void {
      $this->writeParagraphOrder($calculationId, $version, $paragraphKey, $blocks);
    });
  }

  /**
   * @param array<int,array{type:string,id:int,paragraph?:string}> $blocks
   */
  private function applyWorkspace(int $calculationId, string $version, array $blocks): void {
    $expected = $this->repository->expectedWorkspaceBlocks($calculationId, $version);
    $submitted = $this->normalizeSubmitted($blocks, TRUE);
    $byParagraph = [];

    foreach ($submitted as $block) {
      $paragraph = (string) $block['paragraph'];
      $this->assertLeafParagraph($calculationId, $version, $paragraph);
      $byParagraph[$paragraph][] = ['type' => $block['type'], 'id' => $block['id']];
    }

    $submittedKeys = array_keys($submitted);
    $expectedKeys = array_keys($expected);
    sort($submittedKeys);
    sort($expectedKeys);
    if ($submittedKeys !== $expectedKeys) {
      throw new \RuntimeException('Workspace order must contain every current row and recipe exactly once.');
    }

    $this->repository->transactional(function () use ($calculationId, $version, $submitted, $expected, $byParagraph): void {
      foreach ($submitted as $key => $block) {
        $type = (string) $block['type'];
        $id = (int) $block['id'];
        $targetParagraph = (string) $block['paragraph'];
        $currentParagraph = (string) $expected[$key]['paragraph'];
        if ($targetParagraph === $currentParagraph) {
          continue;
        }

        if ($type === 'row') {
          $this->legacyCompatibility->moveRow($calculationId, $version, $id, $targetParagraph);
          $updated = $this->repository->moveRow($calculationId, $version, $id, $targetParagraph);
        }
        else {
          $updated = $this->repository->moveRecipe($calculationId, $version, $id, $targetParagraph);
        }
        if (!$updated) {
          throw new \RuntimeException('Calculation block could not be moved to the target paragraph.');
        }
      }

      foreach ($byParagraph as $paragraph => $paragraphBlocks) {
        $this->writeParagraphOrder($calculationId, $version, $paragraph, $paragraphBlocks);
      }
    });
  }

  /**
   * @param array<int,array{type:string,id:int,paragraph?:string}> $blocks
   * @return array<string,array{type:string,id:int,paragraph?:string}>
   */
  private function normalizeSubmitted(array $blocks, bool $requireParagraph): array {
    $submitted = [];
    foreach ($blocks as $block) {
      $type = (string) ($block['type'] ?? '');
      $id = (int) ($block['id'] ?? 0);
      $paragraph = trim((string) ($block['paragraph'] ?? ''));
      if (!in_array($type, ['row', 'recipe'], TRUE) || $id <= 0 || ($requireParagraph && $paragraph === '')) {
        throw new \InvalidArgumentException($requireParagraph
          ? 'Invalid calculation workspace order payload.'
          : 'Invalid calculation block order payload.');
      }
      $key = $type . ':' . $id;
      if (isset($submitted[$key])) {
        throw new \InvalidArgumentException($requireParagraph
          ? 'Duplicate calculation block in workspace order payload.'
          : 'Duplicate calculation block in order payload.');
      }
      $submitted[$key] = $requireParagraph
        ? ['type' => $type, 'id' => $id, 'paragraph' => $paragraph]
        : ['type' => $type, 'id' => $id];
    }
    return $submitted;
  }

  /** @param array<int,array{type:string,id:int}> $blocks */
  private function writeParagraphOrder(int $calculationId, string $version, string $paragraphKey, array $blocks): void {
    $position = 10;
    foreach ($blocks as $block) {
      $type = (string) $block['type'];
      $id = (int) $block['id'];
      if ($type === 'row') {
        if (!$this->repository->reorderRow($calculationId, $version, $id, $paragraphKey, $position)) {
          throw new \RuntimeException('Calculation row no longer belongs to this calculation paragraph.');
        }
        $this->legacyCompatibility->reorderRow($calculationId, $version, $id, $position);
      }
      else {
        if (!$this->repository->reorderRecipe($calculationId, $version, $id, $paragraphKey, $position)) {
          throw new \RuntimeException('Recipe block no longer belongs to this calculation paragraph.');
        }
      }
      $position += 10;
    }
  }

  private function assertLeafParagraph(int $calculationId, string $version, string $paragraphKey): void {
    if (!$this->repository->isLeafParagraph($calculationId, $version, $paragraphKey)) {
      throw new \RuntimeException('Only leaf paragraphs may contain calculation blocks.');
    }
  }

  private function assertEditable(int $calculationId, string $version, int $actorId): void {
    if (!$this->repository->isEditableVersion($calculationId, $version)) {
      throw new \RuntimeException('Only unlocked draft calculation versions may be reordered.');
    }
    $this->accessGateway->assertCanEditWorkbench($calculationId, $actorId);
  }

}
