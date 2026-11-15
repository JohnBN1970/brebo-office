<?php
declare(strict_types=1);
namespace Brebo\Office\Materials;
use PDO;

/** Persistence adapter to the EXISTING SALES tables; never bootstraps another article catalogue. */
final class PdoMaterialCatalogStore implements MaterialCatalogStore
{
    private readonly string $prefix;
    public function __construct(private readonly PDO $db,string $prefix='')
    {
        if(!preg_match('/^[a-zA-Z0-9_]*$/D',$prefix)||strlen($prefix)>32)throw new \InvalidArgumentException('Ongeldig tabelprefix.');
        $this->prefix=$prefix;
    }
    private function table(string $name):string{return '`'.$this->prefix.$name.'`';}
    private function query(string $sql,array $values=[]):\PDOStatement{$q=$this->db->prepare($sql);$q->execute($values);return $q;}
    public function transaction(callable $operation):mixed
    {
        if($this->db->inTransaction())throw new \RuntimeException('Materiaalpublicatie vraagt een eigen transactie.');
        $this->db->beginTransaction();try{$result=$operation();$this->db->commit();return $result;}catch(\Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
    }
    public function lockSupplier(int $id):array
    {
        $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $row=$this->query('SELECT id,name,active FROM '.$this->table('brebo_supplier').' WHERE id=?'.$lock,[$id])->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new \OutOfBoundsException('Leverancier niet beschikbaar.');return $row;
    }
    public function importByHash(string $hash):?array{$row=$this->query('SELECT id,record_count FROM '.$this->table('brebo_catalog_import').' WHERE source_hash=?',[$hash])->fetch(PDO::FETCH_ASSOC);return $row?:null;}
    public function supplierArticle(int $supplierId,string $articleCode):?array
    {
        $row=$this->query('SELECT sa.id AS supplier_article_id,a.base_unit,sa.active AS supplier_active,a.active AS article_active FROM '.$this->table('brebo_supplier_article').' sa JOIN '.$this->table('brebo_article').' a ON a.id=sa.article_id WHERE sa.supplier_id=? AND sa.supplier_article_no=?',[$supplierId,$articleCode])->fetch(PDO::FETCH_ASSOC);if($row&&((int)$row['supplier_active']!==1||(int)$row['article_active']!==1))throw new \DomainException('Het bestaande artikel is niet actief.');return $row?:null;
    }
    public function createArticle(string $code,array $row,int $now):int
    {
        $this->query('INSERT INTO '.$this->table('brebo_article').' (code,description,search_text,base_unit,cost_category,active,created,changed) VALUES(?,?,?,?,?,?,?,?)',[$code,$row['description'],mb_strtolower($row['description'].' '.$row['article_code']),$row['unit'],'Materiaal',1,$now,$now]);return (int)$this->db->lastInsertId();
    }
    public function createSupplierArticle(int $articleId,int $supplierId,array $row):int
    {
        $this->query('INSERT INTO '.$this->table('brebo_supplier_article').' (article_id,supplier_id,supplier_article_no,order_unit,use_unit,conversion_factor,minimum_order,active) VALUES(?,?,?,?,?,?,?,?)',[$articleId,$supplierId,$row['article_code'],$row['unit'],$row['unit'],'1','1',1]);return (int)$this->db->lastInsertId();
    }
    public function createImport(int $supplierId,string $hash,array $source,array $rows,int $now):int
    {
        $this->query('INSERT INTO '.$this->table('brebo_catalog_import').' (supplier_id,standard,source_name,source_hash,price_date,status,record_count,created) VALUES(?,?,?,?,?,?,?,?)',[$supplierId,'OFFICE_REVIEW_V1',$source['reference'],$hash,min(array_column($rows,'price_date')),'actief',count($rows),$now]);return (int)$this->db->lastInsertId();
    }
    public function createPrice(int $supplierArticleId,int $importId,array $row):void
    {
        $this->query('INSERT INTO '.$this->table('brebo_article_price').' (supplier_article_id,catalog_import_id,valid_from,quantity_from,net_price,currency) VALUES(?,?,?,?,?,?)',[$supplierArticleId,$importId,$row['price_date'],'1',$row['net_price'],$row['currency']]);
    }
    public function recordReview(int $importId,string $actorId,array $source,array $rows,int $now):void
    {
        $this->query('INSERT INTO '.$this->table('brebo_material_import_reviews').' (catalog_import_id,actor_id,source_sha256,source_reference,source_json,reviewed_rows_json,reviewed_at) VALUES(?,?,?,?,?,?,?)',[$importId,$actorId,$source['sha256'],$source['reference'],json_encode($source,JSON_THROW_ON_ERROR),json_encode($rows,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$now]);
    }
}
