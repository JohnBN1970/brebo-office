<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Materials/MaterialImportRows.php';
use Brebo\Office\Materials\MaterialImportRows;
function expectMaterial(bool $yes,string $message):void{if(!$yes)throw new RuntimeException($message);}
function rejectMaterial(callable $fn):void{try{$fn();}catch(InvalidArgumentException|DomainException){return;}throw new RuntimeException('Expected rejection');}
$n=new MaterialImportRows();
$table=$n->proposeText("Leverancier;Prijs;Omschrijving;Artikelnummer;Eenheid\nBouwmarkt;45,00;Schroeven;000123;st");
expectMaterial(count($table['rows'])===1,'arbitrary column order');expectMaterial($table['rows'][0]['article_code']==='000123','leading zeros');
expectMaterial($table['rows'][0]['approved']===false,'recognition never approves');
$row=$table['rows'][0]+['price_quantity'=>'100','discount_percentage'=>'10','currency'=>'EUR','price_date'=>'2026-11-13','price_is_ex_vat'=>true,'number_format'=>'nl'];$row['approved']=true;
$approved=$n->approve($row);expectMaterial($approved['net_price']==='0.4050','45 euros per 100 less 10 percent');
expectMaterial($n->decimal('1.234,56','nl')==='1234.560000','Dutch price');expectMaterial($n->decimal('1,234.56','international')==='1234.560000','international price');
rejectMaterial(fn()=>$n->decimal('1,234'));rejectMaterial(fn()=>$n->decimal('-1'));rejectMaterial(fn()=>$n->decimal('1e3'));rejectMaterial(fn()=>$n->decimal('1.23.4','nl'));
foreach(['price'=>'','article_code'=>'','unit'=>'','price_quantity'=>'0','discount_percentage'=>'100','price_date'=>'2026-02-30','currency'=>'']as$key=>$value)rejectMaterial(fn()=>$n->approve(array_replace($row,[$key=>$value])));
rejectMaterial(fn()=>$n->approve(array_replace($row,['approved'=>false])));rejectMaterial(fn()=>$n->approve(array_replace($row,['price_is_ex_vat'=>false])));
$text=$n->proposeText("Leverancier: Bouwmarkt\nArtikelcode: 000001\nOmschrijving: Ligger\nPrijs: 12,50\nEenheid: m\n\nLeverancier: Bouwmarkt\nArtikelcode: 000002\nOmschrijving: Plaat\nPrijs: 25,00\nEenheid: m2");
expectMaterial(count($text['rows'])===2,'labelled paragraphs');expectMaterial($text['rows'][1]['article_code']==='000002','code in prose');
$unknown=$n->proposeText('Een doos materiaal kost misschien veertig euro.');expectMaterial($unknown['rows']===[]&&count($unknown['issues'])>0,'uncertain prose stays unresolved');
$mapped=$n->proposeTable([['00009','Plaat','12.50','m2']],['article_code'=>0,'description'=>1,'price'=>2,'unit'=>3],['supplier'=>'Bouwmarkt']);expectMaterial(count($mapped['rows'])===1,'manual mapping without header');
$unknownColumns=$n->proposeText("XYZ;ABC;DEF;GHI\n00002;Plaat;12,50;m2");expectMaterial(count($unknownColumns['matrix'])===2,'unrecognized columns remain available for mapping');
echo "MATERIAL_ROWS=PASS\n";
