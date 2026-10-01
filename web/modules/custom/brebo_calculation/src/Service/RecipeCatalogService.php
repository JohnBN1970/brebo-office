<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\RecipeRepositoryInterface;

/** Read-only published recipe catalog for external Calc. */
final class RecipeCatalogService {

  public function __construct(
    private readonly RecipeRepositoryInterface $repository,
  ) {}

  /** @return array<string,mixed> */
  public function publishedCatalog(): array {
    $recipes = [];
    foreach ($this->repository->publishedVersions() as $version) {
      $versionId = (int) $version['id'];
      $published = $this->repository->publishedVersion($versionId);
      if (!$published) {
        continue;
      }
      $recipe = $this->repository->recipe((int) $published['recipe_id']);
      if (!$recipe) {
        continue;
      }

      $parameters = array_map(static fn(array $row): array => [
        'key' => (string) $row['parameter_key'],
        'label' => (string) $row['label'],
        'data_type' => (string) $row['data_type'],
        'unit' => $row['unit'] !== NULL ? (string) $row['unit'] : NULL,
        'default_value' => $row['default_value'],
        'formula' => $row['formula'],
        'required' => (bool) $row['required'],
        'sort_order' => (int) $row['sort_order'],
      ], $this->repository->parameters($versionId));

      $lines = array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'key' => (string) $row['line_key'],
        'type' => (string) $row['line_type'],
        'description' => (string) $row['description'],
        'unit' => $row['unit'] !== NULL ? (string) $row['unit'] : NULL,
        'quantity_formula' => $row['quantity_formula'],
        'waste_pct' => (float) $row['waste_pct'],
        'material_ref' => $row['material_ref'],
        'price_source_ref' => $row['price_source_ref'],
        'unit_cost' => $row['unit_cost'] !== NULL ? (float) $row['unit_cost'] : NULL,
        'sort_order' => (int) $row['sort_order'],
        'metadata' => $row['metadata'],
      ], $this->repository->lines($versionId));

      $recipes[] = [
        'recipe_id' => (int) $recipe['id'],
        'recipe_key' => (string) $recipe['recipe_key'],
        'name' => (string) $recipe['name'],
        'version_id' => $versionId,
        'version' => (string) $published['version'],
        'base_unit' => (string) $published['base_unit'],
        'published' => isset($published['published']) ? (int) $published['published'] : NULL,
        'applicability' => $this->decodeApplicability($published['applicability'] ?? NULL),
        'parameters' => $parameters,
        'lines' => $lines,
      ];
    }

    return [
      'catalog_version' => hash('sha256', json_encode($recipes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
      'recipes' => $recipes,
    ];
  }

  /** @return array<string,mixed>|null */
  private function decodeApplicability(mixed $value): ?array {
    if ($value === NULL || trim((string) $value) === '') {
      return NULL;
    }
    $decoded = json_decode((string) $value, TRUE);
    return is_array($decoded) ? $decoded : NULL;
  }

}
