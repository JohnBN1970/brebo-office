<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Materials/MaterialImportRows.php';
require_once __DIR__.'/../src/Materials/MaterialSourceReader.php';
use Brebo\Office\Materials\MaterialSourceReader;
function materialReaderExpect(bool $yes,string $message):void{if(!$yes)throw new RuntimeException($message);}
$r=new MaterialSourceReader();
$csv=$r->read('materialen.csv','text/csv',"Artikelcode;Omschrijving;Prijs;Eenheid\n00012;Ligger;12,50;m",['supplier'=>'Leverancier']);
materialReaderExpect($csv['rows'][0]['article_code']==='00012','csv code');
$pdf=$r->read('lijst.pdf','application/pdf','%PDF-1.7');materialReaderExpect($pdf['status']==='extraction_unavailable'&&$pdf['rows']===[],'missing provider cannot invent material');
$mock=new MaterialSourceReader(static fn(string $b,string $m,string $f):array=>['status'=>'extracted','text'=>"Artikelcode;Omschrijving;Prijs;Eenheid\nA1;Plaat;15,00;m2",'extractor'=>'fixture']);
materialReaderExpect(count($mock->read('scan.png','image/png','test',['supplier'=>'L'])['rows'])===1,'configured extractor goes through review');
$xml='<Catalog><Supplier><Name>Leverancier</Name></Supplier><TradeItemLine><TradeItemIdentification><SuppliersTradeItemId>00001</SuppliersTradeItemId></TradeItemIdentification><MultiLanguageTradeItemDescription><TradeItemDescription><Description>Schroef</Description></TradeItemDescription></MultiLanguageTradeItemDescription><UseUnitInformation><UseUnitUoM>PCE</UseUnitUoM></UseUnitInformation><PriceInformation><NetPrice>0.45</NetPrice></PriceInformation></TradeItemLine></Catalog>';
$sales=$r->read('sales.xml','application/xml',$xml);materialReaderExpect($sales['rows'][0]['article_code']==='00001'&&!$sales['rows'][0]['approved'],'sales review');
try{$r->read('x.xml','application/xml','<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><x>&e;</x>');throw new RuntimeException('DTD accepted');}catch(DomainException){}
function materialZip(array $parts):string{$p=tempnam(sys_get_temp_dir(),'test-material');$z=new ZipArchive();$z->open($p,ZipArchive::OVERWRITE);foreach($parts as$n=>$v)$z->addFromString($n,$v);$z->close();$b=file_get_contents($p);unlink($p);return $b;}
$doc=materialZip(['word/document.xml'=>'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Artikelcode</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Omschrijving</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Prijs</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Eenheid</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>A1</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Plaat</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>25,00</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>m2</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>']);
materialReaderExpect(count($r->read('lijst.docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',$doc)['rows'])===1,'Word table');
echo "MATERIAL_SOURCE_READER=PASS\n";
