<?php
declare(strict_types=1);
namespace Brebo\Office\Materials;

/** Source-neutral proposals. Recognition never approves an article or guesses missing prices. */
final class MaterialImportRows
{
    private const ALIASES = [
        'supplier'=>['leverancier','supplier','fabrikant','vendor'],
        'article_code'=>['artikelcode','artikelnummer','artikelnr','artnr','artcode','productcode','sku','supplierarticlecode','supplierstradeitemid'],
        'description'=>['omschrijving','artikelomschrijving','beschrijving','description','product','artikel'],
        'price'=>['prijs','nettoprijs','netprijs','eenheidsprijs','unitprice','netprice','inkoopprijs'],
        'unit'=>['eenheid','prijseenheid','unit','uom','priceunit'],
        'price_quantity'=>['prijsper','prijsbasis','prijsaantal','pricequantity','pricebasequantity','per'],
        'discount_percentage'=>['korting','kortingpercentage','discount','discountpercentage'],
        'currency'=>['valuta','currency'],
    ];

    public function proposeTable(array $matrix, array $mapping=[], array $defaults=[]): array
    {
        if(count($matrix)>10000)throw new \DomainException('Materiaallijst bevat meer dan 10.000 regels.');
        $header=null;$headerRow=-1;
        foreach(($mapping===[]?array_slice($matrix,0,50,true):[])as$index=>$row){
            if(!is_array($row))continue;
            $candidate=[];
            foreach($row as$column=>$value){
                $name=$this->header((string)$value);
                foreach(self::ALIASES as$field=>$aliases)if(in_array($name,$aliases,true))$candidate[$field]=$column;
            }
            if(isset($candidate['article_code'],$candidate['description'])&&isset($candidate['price'])){$header=$candidate;$headerRow=$index;break;}
        }
        if($mapping!==[])$header=$mapping;
        if($header===null)return ['rows'=>[],'issues'=>['Kolommen niet eenduidig herkend. Koppel de bronkolommen aan artikelcode, omschrijving, prijs en eenheid.'],'mapping'=>[],'matrix'=>$matrix];
        $result=[];$issues=[];
        foreach($matrix as$index=>$row){
            if($index<=$headerRow||!is_array($row))continue;
            if(implode('',array_map(static fn($v):string=>trim((string)$v),$row))==='')continue;
            $item=$defaults;
            foreach($header as$field=>$column){
                if(!array_key_exists($field,self::ALIASES))throw new \InvalidArgumentException('Onbekend materiaalveld.');
                $item[$field]=trim((string)($row[$column]??''));
            }
            // Repeated headings on subsequent pages are structural, not materials.
            if($this->header((string)($item['article_code']??''))==='artikelcode'||$this->header((string)($item['article_code']??''))==='artikelnummer')continue;
            $item['source_row']=(string)($index+1);$item['raw']=$row;
            $review=[];
            foreach(['supplier','article_code','description','price','unit']as$field)if(trim((string)($item[$field]??''))==='')$review[]='Ontbrekend: '.$field;
            if($review!==[])$issues[]='Regel '.($index+1).': '.implode(', ',$review);
            $item['issues']=$review;$item['approved']=false;$result[]=$item;
        }
        return ['rows'=>$result,'issues'=>$issues,'mapping'=>$header,'matrix'=>$matrix];
    }

    public function proposeText(string $text,array $defaults=[]):array
    {
        if(strlen($text)>8000000)throw new \DomainException('Brontekst is te groot.');
        $text=preg_replace('/^\xEF\xBB\xBF/','',$text)??$text;
        $lines=preg_split('/\r\n|\n|\r/',$text)?:[];
        $unmapped=[];$unmappedScore=0;
        foreach(["\t",';','|',',']as$delimiter){
            $matrix=array_map(static fn(string $line):array=>str_getcsv($line,$delimiter,'"',''),$lines);
            $table=$this->proposeTable($matrix,[],$defaults);
            if($table['mapping']!==[])return $table+['method'=>'table'];
            $score=count(array_filter($matrix,static fn(array $row):bool=>count($row)>1))*min(50,max(array_map('count',$matrix)?:[0]));
            if($score>$unmappedScore){$unmapped=$matrix;$unmappedScore=$score;}
        }
        $rows=[];
        foreach(preg_split('/\n\s*\n/u',$text)?:[]as$block){
            $item=$defaults;
            foreach(preg_split('/\R/u',$block)?:[]as$line){
                if(!preg_match('/^\s*([^:]{1,40}):\s*(.+)$/u',$line,$m))continue;
                $name=$this->header($m[1]);
                foreach(self::ALIASES as$field=>$aliases)if(in_array($name,$aliases,true))$item[$field]=trim($m[2]);
            }
            if(!isset($item['article_code'])||(!isset($item['price'])&&!isset($item['description'])))continue;
            $item['source_fragment']=$block;$item['source_row']=(string)(count($rows)+1);$item['approved']=false;
            $item['issues']=[];
            foreach(['supplier','article_code','description','price','unit']as$field)if(trim((string)($item[$field]??''))==='')$item['issues'][]='Ontbrekend: '.$field;
            $rows[]=$item;
        }
        return ['rows'=>$rows,'issues'=>$rows===[]?['Geen eenduidige materiaalregels herkend. Controleer de bron en de veldkoppeling.']:[],'mapping'=>[],'method'=>'labelled_text','text'=>$text,'matrix'=>$rows===[]?$unmapped:[]];
    }

    /** Explicitly reviewed values only. Prices are normalized per use unit, excluding VAT. */
    public function approve(array $row):array
    {
        if(($row['approved']??null)!==true)throw new \DomainException('Materiaalregel is niet goedgekeurd.');
        foreach(['supplier','article_code','description','unit']as$field){
            if(!is_string($row[$field]??null)||trim($row[$field])==='')throw new \InvalidArgumentException('Materiaalveld ontbreekt: '.$field);
            $row[$field]=trim($row[$field]);
        }
        foreach(['supplier'=>255,'article_code'=>128,'description'=>512,'unit'=>16]as$field=>$max)if(mb_strlen($row[$field])>$max)throw new \InvalidArgumentException('Materiaalveld te lang: '.$field);
        if(($row['price_is_ex_vat']??null)!==true)throw new \DomainException('Bevestig de prijs exclusief btw.');
        $format=$row['number_format']??'auto';
        $price=$this->decimal((string)($row['price']??''),$format);
        $basis=$this->decimal((string)($row['price_quantity']??'1'),$format);
        $discount=$this->decimal((string)($row['discount_percentage']??'0'),$format);
        if(bccomp($price,'0',6)<=0||bccomp($basis,'0',6)<=0||bccomp($discount,'100',6)>=0)throw new \DomainException('Prijs en prijsbasis moeten positief zijn; korting moet lager zijn dan 100%.');
        $net=bcdiv(bcmul($price,bcsub('1',bcdiv($discount,'100',10),10),10),$basis,10);
        $net=bcadd($net,'0.00005',4);
        if(bccomp($net,'0',4)<=0)throw new \DomainException('Genormaliseerde prijs is te klein.');
        $currency=strtoupper(trim((string)($row['currency']??'')));
        if(!preg_match('/^[A-Z]{3}$/D',$currency))throw new \InvalidArgumentException('Valuta ontbreekt.');
        $date=(string)($row['price_date']??'');
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new \InvalidArgumentException('Geldige prijsdatum vereist.');
        return ['supplier'=>$row['supplier'],'article_code'=>$row['article_code'],'description'=>$row['description'],
            'unit'=>$row['unit'],'net_price'=>$net,'currency'=>$currency,'price_date'=>$date,
            'source_price'=>$price,'price_quantity'=>$basis,'discount_percentage'=>$discount,
            'source_row'=>(string)($row['source_row']??''),'source_fragment'=>(string)($row['source_fragment']??'')];
    }

    public function decimal(string $raw,string $format='auto'):string
    {
        if(!in_array($format,['auto','nl','international'],true))throw new \InvalidArgumentException('Onbekende getalnotatie.');
        $raw=trim(str_replace(["\u{00a0}",'€','EUR'],[' ','',''],$raw));
        if($raw===''||strlen($raw)>32||preg_match('/[^0-9.,]/D',$raw))throw new \InvalidArgumentException('Ongeldige prijs of hoeveelheid.');
        if($format==='auto'){
            if(str_contains($raw,',')&&str_contains($raw,'.'))$format=strrpos($raw,',')>strrpos($raw,'.')?'nl':'international';
            elseif(preg_match('/^[1-9][0-9]*[.,][0-9]{3}$/D',$raw))throw new \DomainException('Dubbelzinnige getalnotatie: kies Nederlandse of internationale notatie.');
            else $format=str_contains($raw,',')?'nl':'international';
        }
        $decimal=$format==='nl'?',':'.';$thousands=$format==='nl'?'.':',';
        $parts=explode($decimal,$raw);
        if(count($parts)>2)throw new \InvalidArgumentException('Ongeldige decimalen.');
        if(str_contains($parts[0],$thousands)&&!preg_match('/^[0-9]{1,3}(?:'.preg_quote($thousands,'/').'[0-9]{3})+$/D',$parts[0]))throw new \InvalidArgumentException('Ongeldige duizendtallen.');
        $value=str_replace($thousands,'',$parts[0]).(isset($parts[1])?'.'.$parts[1]:'');
        if(!preg_match('/^[0-9]{1,10}(?:\.[0-9]{1,6})?$/D',$value)||bccomp($value,'9999999999',6)>0)throw new \InvalidArgumentException('Bedrag of hoeveelheid buiten bereik.');
        return bcadd($value,'0',6);
    }

    private function header(string $value):string
    {
        return preg_replace('/[^a-z0-9]/','',mb_strtolower(trim($value)))??'';
    }
}
