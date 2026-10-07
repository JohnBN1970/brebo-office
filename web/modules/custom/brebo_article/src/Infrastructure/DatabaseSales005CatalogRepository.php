<?php

declare(strict_types=1);

namespace Drupal\brebo_article\Infrastructure;

use Drupal\brebo_article\Contract\Sales005CatalogRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for SALES005 catalogue persistence. */
final class DatabaseSales005CatalogRepository implements Sales005CatalogRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function findImportByHash(string $sourceHash): ?array {
    $row = $this->database->select('brebo_catalog_import', 'ci')
      ->fields('ci', ['id', 'record_count', 'status'])
      ->condition('source_hash', $sourceHash)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function activateImport(int $importId): void {
    $this->database->update('brebo_catalog_import')
      ->fields(['status' => 'actief'])
      ->condition('id', $importId)
      ->execute();
  }

  public function transactional(callable $operation): mixed {
    $transaction = $this->database->startTransaction();
    try {
      return $operation();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  public function upsertSupplier(string $code, array $fields, array $insertFields): int {
    $this->database->merge('brebo_supplier')
      ->keys(['code' => $code])
      ->fields($fields)
      ->insertFields($insertFields + $fields)
      ->execute();

    return (int) $this->database->select('brebo_supplier', 's')
      ->fields('s', ['id'])
      ->condition('code', $code)
      ->execute()
      ->fetchField();
  }

  public function createImport(array $fields): int {
    return (int) $this->database->insert('brebo_catalog_import')->fields($fields)->execute();
  }

  public function updateImport(int $importId, array $fields): void {
    $this->database->update('brebo_catalog_import')
      ->fields($fields)
      ->condition('id', $importId)
      ->execute();
  }

  public function articleIdByCode(string $code): ?int {
    $id = $this->database->select('brebo_article', 'a')
      ->fields('a', ['id'])
      ->condition('code', $code)
      ->execute()
      ->fetchField();
    return $id === FALSE ? NULL : (int) $id;
  }

  public function updateArticle(int $articleId, array $fields): void {
    $this->database->update('brebo_article')
      ->fields($fields)
      ->condition('id', $articleId)
      ->execute();
  }

  public function createArticle(array $fields): int {
    return (int) $this->database->insert('brebo_article')->fields($fields)->execute();
  }

  public function upsertSupplierArticle(int $supplierId, string $supplierArticleNo, array $fields): int {
    $this->database->merge('brebo_supplier_article')
      ->keys(['supplier_id' => $supplierId, 'supplier_article_no' => $supplierArticleNo])
      ->fields($fields)
      ->execute();

    return (int) $this->database->select('brebo_supplier_article', 'sa')
      ->fields('sa', ['id'])
      ->condition('supplier_id', $supplierId)
      ->condition('supplier_article_no', $supplierArticleNo)
      ->execute()
      ->fetchField();
  }

  public function upsertArticlePrice(array $keys, array $fields): void {
    $this->database->merge('brebo_article_price')
      ->keys($keys)
      ->fields($fields)
      ->execute();
  }

}
