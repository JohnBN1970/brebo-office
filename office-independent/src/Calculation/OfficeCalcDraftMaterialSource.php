<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;
use Brebo\Office\Materials\{MaterialCatalogReadModel,OfficeMaterialAccess};

/** Read-only catalogue adapter. The browser never supplies a trusted price or supplier. */
final class OfficeCalcDraftMaterialSource
{
    public function __construct(private readonly MaterialCatalogReadModel $catalog,private readonly OfficeMaterialAccess $access){}
    public function choose(string $actor,int $supplierArticle,int $reviewedPrice,string $date):array
    {
        if(!$this->access->allows($actor,'read'))throw new \DomainException('Geen leesrecht op de materialenprijslijst.');
        $article=$this->catalog->currentArticle($supplierArticle,$date);
        if($article===null||(int)$article['price_id']!==$reviewedPrice)throw new OfficeCalcDraftConflict('De geselecteerde materiaalprijs is gewijzigd of niet meer geldig. Controleer de actuele prijslijst en kies de regel opnieuw.');
        if(bccomp((string)$article['net_price'],'0',4)<=0||trim((string)$article['unit'])==='')throw new \DomainException('Controleer eerst de prijs en gebruikseenheid in de prijslijst.');
        $details=['contract'=>'office-material-price-v1','supplier_article_id'=>$supplierArticle,'price_id'=>$reviewedPrice,'import_id'=>(int)$article['import_id'],'supplier'=>(string)$article['supplier'],'article_code'=>(string)$article['article_code'],'original_description'=>(string)$article['description'],'currency'=>'EUR','unit'=>(string)$article['unit'],'net_price'=>(string)$article['net_price'],'selected_at'=>gmdate('c')];
        return ['description'=>mb_substr((string)$article['description'],0,500),'unit'=>(string)$article['unit'],'unit_price'=>(string)$article['net_price'],
            'price_source_type'=>'article','office_source_id'=>(string)$supplierArticle,'source_reference'=>'sales:price:'.$reviewedPrice,'source_supplier'=>(string)$article['supplier'],'source_unit_price'=>(string)$article['net_price'],'source_price_date'=>(string)$article['valid_from'],'source_document_id'=>null,'source_details'=>json_encode($details,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)];
    }
}
