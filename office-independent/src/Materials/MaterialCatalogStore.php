<?php
declare(strict_types=1);
namespace Brebo\Office\Materials;

/** One canonical SALES article store. Calc never receives a database connection. */
interface MaterialCatalogStore
{
    public function transaction(callable $operation):mixed;
    public function lockSupplier(int $id):array;
    public function importByHash(string $hash):?array;
    public function supplierArticle(int $supplierId,string $articleCode):?array;
    public function createArticle(string $code,array $row,int $now):int;
    public function createSupplierArticle(int $articleId,int $supplierId,array $row):int;
    public function createImport(int $supplierId,string $hash,array $source,array $rows,int $now):int;
    public function createPrice(int $supplierArticleId,int $importId,array $row):void;
    public function recordReview(int $importId,string $actorId,array $source,array $rows,int $now):void;
}
