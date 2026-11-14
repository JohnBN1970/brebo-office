<?php
declare(strict_types=1);
namespace Brebo\Office\Materials;

/** Publishes only explicitly reviewed rows, preserving supplier identities and old prices. */
final class MaterialCatalogPublisher
{
    public function __construct(private readonly MaterialCatalogStore $store,private readonly MaterialImportRows $normalizer,private readonly \Closure $canImport){}

    public function publish(string $actorId,int $supplierId,array $source,array $input,int $now):array
    {
        if(($this->canImport)($actorId)!==true)throw new \DomainException('Geen materiaalimportrecht.');
        if($actorId===''||strlen($actorId)>128||$supplierId<1||$now<1)throw new \InvalidArgumentException('Ongeldige materiaalreviewcontext.');
        if(!preg_match('/^[a-f0-9]{64}$/D',(string)($source['sha256']??''))||!is_string($source['reference']??null)||$source['reference']===''||strlen($source['reference'])>255)throw new \InvalidArgumentException('Gecontroleerde bronreferentie ontbreekt.');
        if($input===[]||count($input)>10000)throw new \InvalidArgumentException('Geen geldige materiaalselectie.');
        $rows=[];$keys=[];
        foreach($input as$row){
            if(!is_array($row))throw new \InvalidArgumentException('Ongeldige materiaalregel.');
            if(($row['approved']??false)!==true)continue;
            $normalized=$this->normalizer->approve($row);
            $key=$normalized['article_code'].'|'.$normalized['price_date'].'|'.$normalized['currency'];
            if(isset($keys[$key]))throw new \DomainException('Dubbele artikelcode met dezelfde prijsdatum in de selectie.');
            $keys[$key]=true;$rows[]=$normalized;
        }
        if($rows===[])throw new \DomainException('Selecteer en controleer minimaal één materiaalregel.');
        usort($rows,static fn(array $a,array $b):int=>[$a['article_code'],$a['price_date'],$a['currency']]<=>[$b['article_code'],$b['price_date'],$b['currency']]);
        $hash=hash('sha256',json_encode([$supplierId,$source['sha256'],$source['reference'],$rows],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        return $this->store->transaction(function()use($actorId,$supplierId,$source,$rows,$hash,$now):array{
            $supplier=$this->store->lockSupplier($supplierId);
            if(($supplier['active']??0)!=1)throw new \DomainException('Leverancier is niet actief.');
            foreach($rows as$row)if(mb_strtolower(trim((string)$supplier['name']))!==mb_strtolower($row['supplier']))throw new \DomainException('Herkende leverancier wijkt af van de gekozen leverancier. Controleer de koppeling.');
            $existing=$this->store->importByHash($hash);
            if($existing!==null)return ['status'=>'already_imported','import_id'=>(int)$existing['id'],'records'=>(int)$existing['record_count']];
            $importId=$this->store->createImport($supplierId,$hash,$source,$rows,$now);$created=0;
            foreach($rows as$row){
                $article=$this->store->supplierArticle($supplierId,$row['article_code']);
                if($article!==null){
                    if($this->unit((string)$article['base_unit'])!==$this->unit($row['unit']))throw new \DomainException('Artikel '.$row['article_code'].' heeft een andere bestaande gebruikseenheid. Controleer de omrekening.');
                    $supplierArticleId=(int)$article['supplier_article_id'];
                }else{
                    $code='MAT-'.substr(hash('sha256',$supplierId."\0".$row['article_code']),0,40);
                    $articleId=$this->store->createArticle($code,$row,$now);
                    $supplierArticleId=$this->store->createSupplierArticle($articleId,$supplierId,$row);$created++;
                }
                $this->store->createPrice($supplierArticleId,$importId,$row);
            }
            $this->store->recordReview($importId,$actorId,$source,$rows,$now);
            return ['status'=>'completed','import_id'=>$importId,'records'=>count($rows),'articles_created'=>$created];
        });
    }

    private function unit(string $unit):string
    {
        return match(mb_strtolower(trim($unit))){'pce','pcs','stuk','stuks','st'=>'st','mtr','m1','m¹','m'=>'m','m2','m²'=>'m2','m3','m³'=>'m3',default=>mb_strtolower(trim($unit))};
    }
}
