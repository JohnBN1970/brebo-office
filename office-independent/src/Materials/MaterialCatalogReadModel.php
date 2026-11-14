<?php
declare(strict_types=1);
namespace Brebo\Office\Materials;
use PDO;

/** Reads the verified shared catalogue; callers must enforce Office materials/read. */
final class MaterialCatalogReadModel
{
    public function __construct(private readonly PDO $db,private readonly string $prefix='')
    {
        if(!preg_match('/^[A-Za-z0-9_]{0,32}$/D',$prefix))throw new \InvalidArgumentException('Invalid catalogue prefix');
    }
    private function table(string $name):string{return '`'.$this->prefix.$name.'`';}
    /** @return list<array<string,mixed>> One current EUR base price per supplier article. */
    public function search(string $search,?int $supplierId,string $date,int $page=0):array
    {
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new \InvalidArgumentException('Invalid price date');
        $page=max(0,min(100000,$page));$search=mb_substr(trim($search),0,120);$values=[$date,$date,$date,$date];
        $sql='SELECT sa.id AS supplier_article_id,s.name AS supplier,sa.supplier_article_no AS article_code,a.description,sa.use_unit AS unit,p.net_price,p.valid_from,r.actor_id,r.reviewed_at
            FROM '.$this->table('brebo_supplier_article').' sa
            JOIN '.$this->table('brebo_supplier').' s ON s.id=sa.supplier_id
            JOIN '.$this->table('brebo_article').' a ON a.id=sa.article_id
            JOIN '.$this->table('brebo_article_price').' p ON p.supplier_article_id=sa.id
            JOIN '.$this->table('brebo_catalog_import').' ci ON ci.id=p.catalog_import_id
            LEFT JOIN '.$this->table('brebo_material_import_reviews').' r ON r.catalog_import_id=ci.id
            WHERE s.active=1 AND sa.active=1 AND a.active=1 AND ci.status=\'actief\'
              AND p.currency=\'EUR\' AND p.quantity_from<=1 AND p.valid_from<=?
              AND (p.valid_until IS NULL OR p.valid_until=\'\' OR p.valid_until>=?)
              AND NOT EXISTS(SELECT 1 FROM '.$this->table('brebo_article_price').' newer
                JOIN '.$this->table('brebo_catalog_import').' ni ON ni.id=newer.catalog_import_id
                WHERE newer.supplier_article_id=sa.id AND newer.currency=p.currency AND newer.quantity_from<=1
                  AND ni.status=\'actief\' AND newer.valid_from<=?
                  AND (newer.valid_until IS NULL OR newer.valid_until=\'\' OR newer.valid_until>=?)
                  AND (newer.valid_from>p.valid_from OR(newer.valid_from=p.valid_from AND newer.id>p.id)))';
        if($supplierId!==null){$sql.=' AND sa.supplier_id=?';$values[]=$supplierId;}
        // LOCATE avoids wildcard interpretation of a literal article code or search term.
        if($search!==''){$sql.=' AND (LOCATE(?,a.description)>0 OR LOCATE(?,sa.supplier_article_no)>0 OR LOCATE(?,s.name)>0)';array_push($values,$search,$search,$search);}
        $sql.=' ORDER BY s.name,sa.supplier_article_no,sa.id LIMIT 51 OFFSET '.($page*50);
        $q=$this->db->prepare($sql);$q->execute($values);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
