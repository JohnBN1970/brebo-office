<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/**
 * Persistence boundary for reusable recipe instances.
 */
interface RecipeRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function publishedVersion(int $recipeVersionId): ?array;

  /** @return array<string,mixed>|null */
  public function recipe(int $recipeId): ?array;

  /** @return list<array<string,mixed>> */
  public function parameters(int $recipeVersionId): array;

  /** @return list<array<string,mixed>> */
  public function lines(int $recipeVersionId): array;

  /** @return array<string,mixed>|null */
  public function takeoff(int $takeoffId): ?array;

  /** @return array<string,mixed>|null */
  public function instance(int $instanceId): ?array;

  /** @return array<string,mixed>|null */
  public function instanceLine(int $lineId): ?array;

  /** @return array<string,string> */
  public function instanceParameterValues(int $instanceId): array;

  /** @return list<array{id:int,quantity_formula:string}> */
  public function formulaLines(int $instanceId): array;

  public function nextInstanceSortOrder(int $calculationId, string $version, string $paragraphKey): int;
  public function nextLineSortOrder(int $instanceId): int;

  /** @param array<string,mixed> $values */
  public function insertInstance(array $values): int;

  /** @param array<string,mixed> $values */
  public function insertInstanceParameter(array $values): void;

  /** @param array<string,mixed> $values */
  public function insertInstanceLine(array $values): int;

  /** @param array<string,mixed> $values */
  public function updateInstance(int $instanceId, array $values): void;

  /** @param array<string,mixed> $values */
  public function updateInstanceParameter(int $instanceId, string $key, array $values): void;

  /** @param array<string,mixed> $values */
  public function updateInstanceLine(int $lineId, array $values): void;

  public function isEditableVersion(int $calculationId, string $version): bool;

}
