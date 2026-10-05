<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Infrastructure;

use Drupal\brebo_glass\Contract\GlassPriceRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseGlassPriceRepository implements GlassPriceRepositoryInterface {

  private const CATALOG_TABLES = [
    'brebo_article',
    'brebo_supplier_article',
    'brebo_article_price',
    'brebo_catalog_import',
    'brebo_supplier',
  ];

  public function __construct(private readonly Connection $database) {}

  public function catalogAvailable(): bool {
    foreach (self::CATALOG_TABLES as $table) {
      if (!$this->database->schema()->tableExists($table)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  public function findMaterialPrice(string $productCode, float $quantity, string $date): ?array {
    if (!$this->catalogAvailable()) {
      return NULL;
    }

    $query = $this->database->select('brebo_article', 'a');
    $query->innerJoin('brebo_supplier_article', 'sa', 'sa.article_id = a.id');
    $query->innerJoin('brebo_supplier', 's', 's.id = sa.supplier_id');
    $query->innerJoin('brebo_article_price', 'p', 'p.supplier_article_id = sa.id');
    $query->innerJoin('brebo_catalog_import', 'ci', 'ci.id = p.catalog_import_id');
    $query->fields('a', ['id', 'code', 'description', 'base_unit']);
    $query->addField('sa', 'id', 'supplier_article_id');
    $query->fields('sa', ['supplier_article_no', 'use_unit']);
    $query->addField('s', 'name', 'supplier_name');
    $query->addField('p', 'id', 'price_id');
    $query->fields('p', ['net_price', 'currency', 'valid_from', 'valid_until', 'quantity_from']);
    $query->addField('ci', 'id', 'catalog_import_id');
    $query
      ->condition('a.code', $productCode)
      ->condition('a.active', 1)
      ->condition('sa.active', 1)
      ->condition('s.active', 1)
      ->condition('p.valid_from', $date, '<=');
    $validity = $query->orConditionGroup()
      ->condition('p.valid_until', NULL, 'IS NULL')
      ->condition('p.valid_until', $date, '>=');
    $query
      ->condition($validity)
      ->condition('p.quantity_from', max(1.0, $quantity), '<=')
      ->orderBy('p.valid_from', 'DESC')
      ->orderBy('p.quantity_from', 'DESC')
      ->range(0, 1);

    $row = $query->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function snapshotAvailable(): bool {
    return $this->database->schema()->tableExists('brebo_calculation_article_snapshot');
  }

  public function snapshotExists(int $calculationLineId): bool {
    if (!$this->snapshotAvailable()) {
      return FALSE;
    }
    return (bool) $this->database
      ->select('brebo_calculation_article_snapshot', 's')
      ->condition('calculation_line_id', $calculationLineId)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function insertSnapshot(array $values): void {
    $this->database
      ->insert('brebo_calculation_article_snapshot')
      ->fields($values)
      ->execute();
  }

}
