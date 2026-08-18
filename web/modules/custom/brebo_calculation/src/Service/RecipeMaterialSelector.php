<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/** Pins a catalog article and historical price to a recipe instance line. */
final class RecipeMaterialSelector {

  public function __construct(private readonly Connection $database) {}

  /**
   * @param array<string,mixed> $selection
   */
  public function select(int $recipeInstanceLineId, array $selection, AccountInterface $actor): void {
    $line = $this->database->select('brebo_calculation_recipe_instance_line', 'l')
      ->fields('l')
      ->condition('id', $recipeInstanceLineId)
      ->execute()
      ->fetchAssoc();
    if (!$line) {
      throw new \InvalidArgumentException('Recipe instance line not found.');
    }
    if (!in_array(strtolower((string) $line['line_type']), ['material', 'materiaal'], TRUE)) {
      throw new \InvalidArgumentException('Only material recipe lines can use catalog articles.');
    }

    $instance = $this->database->select('brebo_calculation_recipe_instance', 'i')
      ->fields('i', ['calculation_id', 'calculation_version'])
      ->condition('id', (int) $line['recipe_instance_id'])
      ->execute()
      ->fetchAssoc();
    if (!$instance) {
      throw new \RuntimeException('Recipe instance not found.');
    }
    $this->assertEditableCalculation((int) $instance['calculation_id'], (string) $instance['calculation_version']);

    $articleId = (int) ($selection['article_id'] ?? 0);
    $supplierArticleId = (int) ($selection['supplier_article_id'] ?? 0);
    $priceId = (int) ($selection['price_id'] ?? 0);
    $catalogImportId = (int) ($selection['catalog_import_id'] ?? 0);
    if ($articleId <= 0 || $supplierArticleId <= 0 || $priceId <= 0 || $catalogImportId <= 0) {
      throw new \InvalidArgumentException('Complete article selection is required.');
    }

    $query = $this->database->select('brebo_supplier_article', 'sa');
    $query->join('brebo_article', 'a', 'a.id = sa.article_id');
    $query->join('brebo_supplier', 's', 's.id = sa.supplier_id');
    $query->join('brebo_article_price', 'p', 'p.supplier_article_id = sa.id');
    $query->join('brebo_catalog_import', 'ci', 'ci.id = p.catalog_import_id');
    $query->fields('a', ['id', 'code', 'description', 'base_unit']);
    $query->addField('sa', 'id', 'supplier_article_id');
    $query->fields('sa', ['supplier_article_no', 'use_unit']);
    $query->addField('s', 'name', 'supplier_name');
    $query->addField('p', 'id', 'price_id');
    $query->fields('p', ['net_price', 'valid_from']);
    $query->addField('ci', 'id', 'catalog_import_id');
    $query->condition('a.id', $articleId);
    $query->condition('sa.id', $supplierArticleId);
    $query->condition('p.id', $priceId);
    $query->condition('ci.id', $catalogImportId);
    $record = $query->execute()->fetchAssoc();
    if (!$record) {
      throw new \InvalidArgumentException('Selected article and price do not belong together.');
    }

    $materialRef = sprintf('article:%d:supplier_article:%d', $articleId, $supplierArticleId);
    $priceRef = sprintf('article_price:%d:catalog:%d:date:%s', $priceId, $catalogImportId, (string) $record['valid_from']);
    $unit = trim((string) ($record['use_unit'] ?: $record['base_unit']));

    $this->database->update('brebo_calculation_recipe_instance_line')
      ->fields([
        'description' => (string) $record['description'],
        'unit' => $unit !== '' ? $unit : NULL,
        'material_ref' => $materialRef,
        'price_source_ref' => $priceRef,
        'unit_cost' => (float) $record['net_price'],
      ])
      ->condition('id', $recipeInstanceLineId)
      ->execute();
  }

  /** @return array<string,mixed>|null */
  public function selectedArticle(int $recipeInstanceLineId): ?array {
    $line = $this->database->select('brebo_calculation_recipe_instance_line', 'l')
      ->fields('l', ['material_ref', 'price_source_ref'])
      ->condition('id', $recipeInstanceLineId)
      ->execute()
      ->fetchAssoc();
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

  private function assertEditableCalculation(int $calculationId, string $version): void {
    $record = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['status', 'locked_at'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()
      ->fetchAssoc();
    if (!$record || $record['status'] !== 'draft' || $record['locked_at'] !== NULL) {
      throw new \RuntimeException('Only unlocked draft calculation versions may be changed.');
    }
  }

}
