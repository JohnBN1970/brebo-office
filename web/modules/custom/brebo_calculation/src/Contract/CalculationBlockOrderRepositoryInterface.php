<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface CalculationBlockOrderRepositoryInterface {
  public function transactional(callable $callback): mixed;
  public function isEditableVersion(int $calculationId, string $version): bool;

  /** @return array<string,array{type:string,id:int}> */
  public function expectedBlocks(int $calculationId, string $version, string $paragraphKey): array;

  /** @return array<string,array{type:string,id:int,paragraph:string}> */
  public function expectedWorkspaceBlocks(int $calculationId, string $version): array;

  public function isLeafParagraph(int $calculationId, string $version, string $paragraphKey): bool;

  public function moveRow(int $calculationId, string $version, int $rowId, string $paragraphKey): bool;
  public function moveRecipe(int $calculationId, string $version, int $recipeId, string $paragraphKey): bool;
  public function reorderRow(int $calculationId, string $version, int $rowId, string $paragraphKey, int $sortOrder): bool;
  public function reorderRecipe(int $calculationId, string $version, int $recipeId, string $paragraphKey, int $sortOrder): bool;
}
