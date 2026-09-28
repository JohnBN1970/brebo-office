<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface RecipeMaterialRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function recipeLine(int $lineId): ?array;

  /** @return array<string,mixed>|null */
  public function recipeInstance(int $instanceId): ?array;

  /** @return array<string,mixed>|null */
  public function articleSelection(int $articleId, int $supplierArticleId, int $priceId, int $catalogImportId): ?array;

  /** @param array<string,mixed> $values */
  public function updateRecipeLine(int $lineId, array $values): void;

  /** @return array<string,mixed>|null */
  public function selectedRefs(int $lineId): ?array;

  public function isEditableVersion(int $calculationId, string $version): bool;
}
