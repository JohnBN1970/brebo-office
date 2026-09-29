<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\RecipeMaterialRepositoryInterface;
use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;

/** Pins a catalog article and historical price to a recipe instance line. */
final class RecipeMaterialSelector {

  public function __construct(
    private readonly RecipeMaterialRepositoryInterface $repository,
    private readonly CalculationAccessGatewayInterface $accessGateway,
  ) {}

  /**
   * @param array<string,mixed> $selection
   */
  public function select(int $recipeInstanceLineId, array $selection, int $actorId): void {
    $line = $this->repository->recipeLine($recipeInstanceLineId);
    if (!$line) {
      throw new \InvalidArgumentException('Recipe instance line not found.');
    }
    if (!in_array(strtolower((string) $line['line_type']), ['material', 'materiaal'], TRUE)) {
      throw new \InvalidArgumentException('Only material recipe lines can use catalog articles.');
    }

    $instance = $this->repository->recipeInstance((int) $line['recipe_instance_id']);
    if (!$instance) {
      throw new \RuntimeException('Recipe instance not found.');
    }
    $this->assertEditableCalculation((int) $instance['calculation_id'], (string) $instance['calculation_version'], $actorId);

    $articleId = (int) ($selection['article_id'] ?? 0);
    $supplierArticleId = (int) ($selection['supplier_article_id'] ?? 0);
    $priceId = (int) ($selection['price_id'] ?? 0);
    $catalogImportId = (int) ($selection['catalog_import_id'] ?? 0);
    if ($articleId <= 0 || $supplierArticleId <= 0 || $priceId <= 0 || $catalogImportId <= 0) {
      throw new \InvalidArgumentException('Complete article selection is required.');
    }

    $record = $this->repository->articleSelection($articleId, $supplierArticleId, $priceId, $catalogImportId);
    if (!$record) {
      throw new \InvalidArgumentException('Selected article and price do not belong together.');
    }

    $materialRef = sprintf('article:%d:supplier_article:%d', $articleId, $supplierArticleId);
    $priceRef = sprintf('article_price:%d:catalog:%d:date:%s', $priceId, $catalogImportId, (string) $record['valid_from']);
    $unit = trim((string) ($record['use_unit'] ?: $record['base_unit']));

    $this->repository->updateRecipeLine($recipeInstanceLineId, [
      'description' => (string) $record['description'],
      'unit' => $unit !== '' ? $unit : NULL,
      'material_ref' => $materialRef,
      'price_source_ref' => $priceRef,
      'unit_cost' => (float) $record['net_price'],
    ]);
  }

  /** @return array<string,mixed>|null */
  public function selectedArticle(int $recipeInstanceLineId): ?array {
    $line = $this->repository->selectedRefs($recipeInstanceLineId);
    if (!$line || !preg_match('/article:(\d+):supplier_article:(\d+)/', (string) $line['material_ref'], $materialMatch) || !preg_match('/article_price:(\d+):catalog:(\d+):date:([^:]+)/', (string) $line['price_source_ref'], $priceMatch)) {
      return NULL;
    }
    return [
      'article_id' => (int) $materialMatch[1],
      'supplier_article_id' => (int) $materialMatch[2],
      'price_id' => (int) $priceMatch[1],
      'catalog_import_id' => (int) $priceMatch[2],
      'price_date' => (string) $priceMatch[3],
    ];
  }

  private function assertEditableCalculation(int $calculationId, string $version, int $actorId): void {
    $this->accessGateway->assertCanEditWorkbench($calculationId, $actorId);
    if (!$this->repository->isEditableVersion($calculationId, $version)) {
      throw new \RuntimeException('Only unlocked draft calculation versions may be changed.');
    }
  }
}
