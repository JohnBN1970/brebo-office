<?php

declare(strict_types=1);

namespace Drupal\brebo_article\Contract;

/** Persistence boundary for SALES005 catalogue imports. */
interface Sales005CatalogRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function findImportByHash(string $sourceHash): ?array;

  public function activateImport(int $importId): void;

  /** @template T @param callable():T $operation @return T */
  public function transactional(callable $operation): mixed;

  /** @param array<string,mixed> $fields @param array<string,mixed> $insertFields */
  public function upsertSupplier(string $code, array $fields, array $insertFields): int;

  /** @param array<string,mixed> $fields */
  public function createImport(array $fields): int;

  /** @param array<string,mixed> $fields */
  public function updateImport(int $importId, array $fields): void;

  public function articleIdByCode(string $code): ?int;

  /** @param array<string,mixed> $fields */
  public function updateArticle(int $articleId, array $fields): void;

  /** @param array<string,mixed> $fields */
  public function createArticle(array $fields): int;

  /** @param array<string,mixed> $fields */
  public function upsertSupplierArticle(int $supplierId, string $supplierArticleNo, array $fields): int;

  /** @param array<string,mixed> $keys @param array<string,mixed> $fields */
  public function upsertArticlePrice(array $keys, array $fields): void;

}
