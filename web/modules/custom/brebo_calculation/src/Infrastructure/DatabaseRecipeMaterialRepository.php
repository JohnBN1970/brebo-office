<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\RecipeMaterialRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseRecipeMaterialRepository implements RecipeMaterialRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function recipeLine(int $lineId): ?array {
    $row = $this->database->select('brebo_calculation_recipe_instance_line', 'l')
      ->fields('l')
      ->condition('id', $lineId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function recipeInstance(int $instanceId): ?array {
    $row = $this->database->select('brebo_calculation_recipe_instance', 'i')
      ->fields('i', ['calculation_id', 'calculation_version'])
      ->condition('id', $instanceId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function articleSelection(int $articleId, int $supplierArticleId, int $priceId, int $catalogImportId): ?array {
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
    $row = $query->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function updateRecipeLine(int $lineId, array $values): void {
    $this->database->update('brebo_calculation_recipe_instance_line')
      ->fields($values)
      ->condition('id', $lineId)
      ->execute();
  }

  public function selectedRefs(int $lineId): ?array {
    $row = $this->database->select('brebo_calculation_recipe_instance_line', 'l')
      ->fields('l', ['material_ref', 'price_source_ref'])
      ->condition('id', $lineId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function isEditableVersion(int $calculationId, string $version): bool {
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['status', 'locked_at'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()
      ->fetchAssoc();
    return (bool) ($row && $row['status'] === 'draft' && $row['locked_at'] === NULL);
  }
}
