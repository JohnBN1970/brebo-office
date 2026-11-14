<?php
declare(strict_types=1);
namespace Brebo\Office\Materials;

/** Adapters produce proposals; only MaterialImportRows::approve permits publication. */
final class MaterialSourceReader
{
    /** @param ?\Closure(string,string,string):array $documentExtractor configured Office extraction adapter */
    public function __construct(private readonly ?\Closure $documentExtractor=null){}

    public function read(string $filename,string $mime,string $bytes,array $defaults=[]):array
    {
        if($bytes===''||strlen($bytes)>20000000)throw new \DomainException('Bronbestand ontbreekt of is groter dan 20 MB.');
        $originalHash=hash('sha256',$bytes);$normalizer=new MaterialImportRows();$extension=strtolower(pathinfo($filename,PATHINFO_EXTENSION));
        if(in_array($extension,['csv','tsv','txt'],true)||in_array($mime,['text/plain','text/csv','text/tab-separated-values'],true)){
            if(!mb_check_encoding($bytes,'UTF-8'))$bytes=mb_convert_encoding($bytes,'UTF-8','Windows-1252');
            return $normalizer->proposeText($bytes,$defaults)+['source_hash'=>$originalHash,'status'=>'review'];
        }
        if($extension==='xml'||in_array($mime,['application/xml','text/xml'],true))return $this->xmlRows($bytes,$defaults);
        if(in_array($extension,['xlsx','docx'],true))return $this->officeArchive($bytes,$extension,$defaults);
        if(in_array($mime,['application/pdf','image/jpeg','image/png','image/webp','image/heic','image/heif'],true)){
            if($this->documentExtractor===null)return ['status'=>'extraction_unavailable','rows'=>[],'issues'=>['De Office-documentherkenning is nog niet beschikbaar voor dit bestand.'],'source_hash'=>hash('sha256',$bytes)];
            $result=($this->documentExtractor)($bytes,$mime,$filename);
            if(($result['status']??null)!=='extracted'||trim((string)($result['text']??''))==='')return ['status'=>'extraction_incomplete','rows'=>[],'issues'=>['De bron is nog niet betrouwbaar uitgelezen.'],'source_hash'=>hash('sha256',$bytes)];
            return $normalizer->proposeText((string)$result['text'],$defaults)+['status'=>'review','source_hash'=>hash('sha256',$bytes),'extraction'=>$result['extractor']??'office_managed'];
        }
        if($extension==='eml')return ['status'=>'mail_selection_required','rows'=>[],'issues'=>['Selecteer de materiaallijst of offertebijlage vanuit het bestaande Office-e-maildossier.'],'source_hash'=>hash('sha256',$bytes)];
        return ['status'=>'unsupported','rows'=>[],'issues'=>['Dit bestandsformaat heeft nog een Office-extractieadapter nodig.'],'source_hash'=>hash('sha256',$bytes)];
    }

    private function xml(string $bytes):\SimpleXMLElement
    {
        if(preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i',$bytes))throw new \DomainException('XML met DTD of externe entiteiten is niet toegestaan.');
        $old=libxml_use_internal_errors(true);
        try{$xml=simplexml_load_string($bytes,\SimpleXMLElement::class,LIBXML_NONET);if($xml===false)throw new \InvalidArgumentException('Ongeldige XML.');return $xml;}
        finally{libxml_clear_errors();libxml_use_internal_errors($old);}
    }

    private function value(\SimpleXMLElement $node,string $path):string
    {
        $names=explode('/',$path);$xpath='./'.implode('/',array_map(static fn(string $name):string=>'*[local-name()="'.$name.'"]',$names));
        $nodes=$node->xpath($xpath);return isset($nodes[0])?trim((string)$nodes[0]):'';
    }

    private function xmlRows(string $bytes,array $defaults):array
    {
        $xml=$this->xml($bytes);$rows=[];$issues=[];
        $supplier=$xml->xpath('//*[local-name()="Supplier"]/*[local-name()="Name"]');
        $supplierName=isset($supplier[0])?trim((string)$supplier[0]):($defaults['supplier']??'');
        foreach($xml->xpath('//*[local-name()="TradeItemLine"]')?:[]as$line){
            $price=$line->xpath('./*[local-name()="PriceInformation"]');
            foreach($price?:[]as$entry){
                $net=$this->value($entry,'NetPrice');if($net==='')continue;
                $factor=$this->value($line,'OrderConditions/PriceToOrderUnitFactor');
                $rows[]=$defaults+[];
                $index=count($rows)-1;
                $rows[$index]=array_replace($defaults,[
                    'supplier'=>$supplierName,'article_code'=>$this->value($line,'TradeItemIdentification/SuppliersTradeItemId'),
                    'description'=>$this->value($line,'MultiLanguageTradeItemDescription/TradeItemDescription/Description'),
                    'unit'=>$this->value($line,'UseUnitInformation/UseUnitUoM')?:$this->value($line,'OrderConditions/OrderUoM'),
                    'price'=>$net,'price_quantity'=>$factor!==''&&$factor!=='1'?'':'1',
                    'source_conversion_factor'=>$factor,'price_date'=>$this->value($entry,'StartDatePriceInformation')?:($defaults['price_date']??''),
                    'source_row'=>(string)($index+1),'approved'=>false,'issues'=>$factor!==''&&$factor!=='1'?['Controleer de prijsbasis en omrekenfactor uit de SALES-bron.']:[],
                ]);
            }
        }
        if($rows===[])$issues[]='Geen SALES-artikelregels met een expliciete nettoprijs gevonden. Andere XML vraagt veldkoppeling of een aanvullende adapter.';
        return ['status'=>'review','rows'=>$rows,'issues'=>$issues,'source_hash'=>hash('sha256',$bytes),'method'=>'sales_xml'];
    }

    private function officeArchive(string $bytes,string $extension,array $defaults):array
    {
        if(!class_exists(\ZipArchive::class))throw new \RuntimeException('ZIP-ondersteuning ontbreekt.');
        $path=tempnam(sys_get_temp_dir(),'office-material-');if($path===false)throw new \RuntimeException('Tijdelijke bron kon niet worden geopend.');
        try{
            file_put_contents($path,$bytes);$zip=new \ZipArchive();
            if($zip->open($path)!==true)throw new \InvalidArgumentException('Ongeldig Office-bestand.');
            try{
                $total=0;if($zip->numFiles>2000)throw new \DomainException('Te veel archiefonderdelen.');
                for($i=0;$i<$zip->numFiles;$i++){
                    $stat=$zip->statIndex($i);$name=(string)$stat['name'];$total+=(int)$stat['size'];
                    if($total>30000000||str_contains($name,'..')||str_starts_with($name,'/')||str_contains($name,'\\'))throw new \DomainException('Onveilig of te groot archief.');
                    if(preg_match('/\.(?:xml|rels)$/i',$name))$this->xml((string)$zip->getFromIndex($i));
                }
                if($extension==='docx'){
                    $document=$zip->getFromName('word/document.xml');if(!is_string($document))throw new \InvalidArgumentException('Word-document ontbreekt.');
                    $xml=$this->xml($document);$lines=[];
                    foreach($xml->xpath('//*[local-name()="body"]/*')?:[]as$block){
                        if($block->getName()==='tbl')foreach($block->xpath('./*[local-name()="tr"]')?:[]as$row){
                            $cells=[];foreach($row->xpath('./*[local-name()="tc"]')?:[]as$cell)$cells[]=implode(' ',array_map(static fn($node):string=>(string)$node,$cell->xpath('.//*[local-name()="t"]')?:[]));
                            $lines[]=implode("\t",$cells);
                        }
                        else $lines[]=implode(' ',array_map(static fn($node):string=>(string)$node,$block->xpath('.//*[local-name()="t"]')?:[]));
                    }
                    return (new MaterialImportRows())->proposeText(implode("\n",$lines),$defaults)+['status'=>'review','source_hash'=>hash('sha256',$bytes),'method'=>'docx'];
                }
                $formula=false;
                for($i=0;$i<$zip->numFiles;$i++)if(preg_match('~^xl/worksheets/sheet[0-9]+\.xml$~',(string)$zip->getNameIndex($i))){
                    $content=$zip->getFromIndex($i);$this->xml((string)$content);if(preg_match('/<f(?:\s|>)/',(string)$content))$formula=true;
                }
            }finally{$zip->close();}
            require_once dirname(__DIR__).'/Inzet/OnSiteXlsxCellReader.php';
            $book=(new \Brebo\Office\Inzet\OnSiteXlsxCellReader())->read($path,2000,30000000);$rows=[];$issues=[];$unmapped=[];
            foreach($book['cells']as$sheet=>$cells){
                $matrix=[];
                foreach($cells as$address=>$value){
                    if(!preg_match('/^([A-Z]+)([1-9][0-9]*)$/D',$address,$m))continue;
                    $column=0;foreach(str_split($m[1])as$letter)$column=$column*26+ord($letter)-64;
                    $matrix[(int)$m[2]-1][$column-1]=$value;
                }
                ksort($matrix);foreach($matrix as&$row)ksort($row);unset($row);
                $proposal=(new MaterialImportRows())->proposeTable($matrix,[],$defaults);
                foreach($proposal['rows']as$row){$row['source_row']=$sheet.':'.$row['source_row'];$rows[]=$row;}
                foreach($proposal['issues']as$issue)$issues[]=$sheet.': '.$issue;
                if($proposal['rows']===[]&&$matrix!==[])$unmapped[]=['sheet'=>$sheet,'matrix'=>$matrix];
            }
            if($formula)$issues[]='Excel bevat formules: getoonde cachewaarden zijn niet opnieuw berekend en moeten worden gecontroleerd.';
            return ['status'=>'review','rows'=>$rows,'issues'=>$issues,'source_hash'=>hash('sha256',$bytes),'method'=>'xlsx','unmapped_tables'=>$unmapped,'matrix'=>$rows===[]?($unmapped[0]['matrix']??[]):[]];
        }finally{@unlink($path);}
    }
}
