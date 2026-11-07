<?php
declare(strict_types=1);
namespace Brebo\Office\Sales;
require_once __DIR__.'/OfficeSalesBilling.php';

/** Local PDF export of the authorized, saved Office invoice; no provider calls. */
final class OfficeSalesPdf {
  private array $pages=[];
  private string $page='';
  private float $y=0;
  private bool $inclusive=false;
  private static array $fonts=[];
  private static function fonts(): array {
    if(self::$fonts===[])self::$fonts=json_decode(file_get_contents(__DIR__.'/brebo-fonts.json'),true,16,JSON_THROW_ON_ERROR);
    return self::$fonts;
  }
  private static function encoded(string $text): string {
    $text=preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u','',$text)??'';
    $bytes=iconv('UTF-8','Windows-1252//TRANSLIT',$text);
    if($bytes===false)throw new \RuntimeException('PDF text encoding unavailable');
    return $bytes;
  }
  private static function width(string $bytes,float $size,bool $bold=false): float {
    $widths=self::fonts()[$bold?'bold':'regular']['widths'];
    $width=0;foreach(str_split($bytes) as $char)$width+=$widths[ord($char)]??556;
    return $width*$size/1000;
  }
  private function text(float $x,float $y,string $text,float $size=10,bool $bold=false,?float $right=null): void {
    $bytes=self::encoded($text);
    if($right!==null)$x=$right-self::width($bytes,$size,$bold);
    $escaped=str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$bytes);
    $this->page.=sprintf("BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",$bold?'F2':'F1',$size,$x,842-$y,$escaped);
  }
  private function rule(float $y): void { $this->page.=sprintf("0.82 G 42 %.2F m 553 %.2F l S 0.12 g\n",842-$y,842-$y); }
  private static function wrap(string $text,float $width,float $size=10): array {
    $result=[];
    foreach(preg_split('/\R/u',$text)?:[''] as $paragraph) {
      $line='';
      foreach(preg_split('/\s+/u',trim($paragraph),-1,PREG_SPLIT_NO_EMPTY)?:[] as $word) {
        $candidate=$line===''?$word:$line.' '.$word;
        if($line!=='' && self::width(self::encoded($candidate),$size)>$width){$result[]=$line;$line='';}
        foreach(preg_split('//u',$word,-1,PREG_SPLIT_NO_EMPTY)?:[] as $char) {
          if($line!=='' && self::width(self::encoded($line.$char),$size)>$width){$result[]=$line;$line='';}
          $line.=$char;
        }
        $line.=' ';
      }
      $result[]=rtrim($line);
    }
    return $result?:[''];
  }
  private function logo(float $x,float $top,float $width): void {
    $height=$width*0.3;
    $this->page.=sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Logo Do Q\n",$width,$height,$x,842-$top-$height);
  }
  private static function date(string $date): string {return (new \DateTimeImmutable($date))->format('d-m-Y');}
  private function heading(array $invoice,bool $first): void {
    if($this->page!=='')$this->pages[]=$this->page;
    $this->page="0.12 g\n";
    $draft=($invoice['invoice_number']??'')==='';
    $this->logo(35,43,172.5);
    $company=[['BREBO Bouw en Advies B.V.',56,true],['Plaza 25A',72,false],['4782 SL Moerdijk',88,false],['085 - 500 38 38',119,false],['info@brebobv.nl',135,false],['brebobv.nl',151,false],['KvK: 92123139',182,false],['BTW: NL865894607.B01',198,false],['IBAN: NL19 ABNA 0894 3515 67',214,false]];
    foreach($company as [$text,$y,$bold])$this->text(0,$y,$text,9,$bold,561);
    if($first) {
      $customer=json_decode($invoice['customer_json'],true,32,JSON_THROW_ON_ERROR);
      $address=array_filter([(string)($customer['legal_name']?:$customer['display_name']),(string)$customer['street'],trim($customer['postal_code'].' '.$customer['city']),(string)($customer['country_code']??'')]);
      $ay=191;$firstAddress=true;
      foreach($address as $part)foreach(self::wrap($part,290) as $line){$this->text(35,$ay,$line,10,$firstAddress);$ay+=15;$firstAddress=false;}
      $this->y=max(291,$ay+32);
      $this->text(35,$this->y,$draft?'CONCEPTFACTUUR #'.$invoice['id']:'Factuur: '.$invoice['invoice_number'],12,true);
      $this->y+=19;
      $this->text(399,$this->y,'Factuurdatum:',9,true);
      $this->text(0,$this->y,self::date($invoice['invoice_date']),9,false,561);
      $this->text(399,$this->y+17,'Vervaldatum:',9,true);
      $this->text(0,$this->y+17,self::date($invoice['due_date']),9,false,561);
      $refY=$this->y;
      $context=OfficeSalesBilling::documentContext($invoice);
      foreach(['Project'=>$context['project'],'Contract'=>$context['contract'],'Uw kenmerk'=>(string)($invoice['reference']??'')] as $label=>$value)if($value!=='')foreach(self::wrap($label.': '.$value,340,9) as $line){$this->text(35,$refY,$line,9);$refY+=13;}
      $this->y=max($this->y+56,$refY+24);
      if($draft){$this->text(35,$this->y-12,'Concept - nog niet verzonden. Geen betaalverzoek.',9);}
    } else {$this->y=260;}
    $this->tableHead();
  }
  private function tableHead(): void {
    foreach([[69,'Omschrijving'],[327,$this->inclusive?'Prijs incl.':'Prijs excl.'],[421,'Totaal'],[525,'BTW']] as [$x,$label])$this->text($x,$this->y,$label,9,true);
    $this->y+=6;$this->rule($this->y);$this->y+=17;
  }
  private static function money(int $cents): string {return '€ '.number_format($cents/100,2,',','.');}
  public static function render(array $invoice): string {
    $invoice=OfficeSalesBilling::defaults($invoice);$pdf=new self();$pdf->inclusive=$invoice['price_mode']==='incl';$pdf->heading($invoice,true);
    $lines=json_decode($invoice['lines_json'],true,32,JSON_THROW_ON_ERROR);$tax=[];
    foreach($lines as $line) {
      $wrapped=self::wrap((string)$line['description'],244);
      if($pdf->y+count($wrapped)*13+12>735)$pdf->heading($invoice,false);
      $first=true;
      foreach($wrapped as $description) {
        if($pdf->y>735)$pdf->heading($invoice,false);
        $pdf->text(69,$pdf->y,$description);
        if($first){
          $pdf->text(42,$pdf->y,(string)$line['quantity'].'x',9);
          $price=(string)$line['price'];[$euros,$fraction]=array_pad(explode('.',$price),2,'');
          $priceCents=(int)$euros*100+(int)str_pad($fraction,2,'0');
          $pdf->text(0,$pdf->y,self::money($priceCents),9,false,368);
          $pdf->text(0,$pdf->y,'BTW '.$line['vat'].'%',9,false,553);
          $pdf->text(0,$pdf->y,self::money((int)$line['net_cents']),9,false,458);$first=false;
        }
        $pdf->y+=13;
      }
      $pdf->y+=10;
      $rate=(string)$line['vat'];$tax[$rate]??=['net'=>0,'vat'=>0];$tax[$rate]['net']+=(int)$line['net_cents'];$tax[$rate]['vat']+=(int)$line['vat_cents'];
    }
    if($pdf->y+110+count($tax)*20>750)$pdf->heading($invoice,false);
    $pdf->y+=21;
    $pdf->text(0,$pdf->y,'Subtotaal excl. BTW',10,false,368);$pdf->text(0,$pdf->y,self::money((int)$invoice['net_cents']),10,false,458);
    foreach($tax as $rate=>$amount){$pdf->y+=20;$pdf->text(0,$pdf->y,'BTW '.$rate.'% over '.self::money($amount['net']),9,false,368);$pdf->text(0,$pdf->y,self::money($amount['vat']),10,false,458);}
    $pdf->y+=20;$pdf->text(0,$pdf->y,'Totaal BTW',10,false,368);$pdf->text(0,$pdf->y,self::money((int)$invoice['vat_cents']),10,false,458);
    $pdf->y+=25;$pdf->rule($pdf->y-15);$pdf->text(0,$pdf->y,'Totaal',11,true,368);$pdf->text(0,$pdf->y,self::money((int)$invoice['gross_cents']),11,false,458);
    if((int)$invoice['g_cents']>0){
      if($pdf->y+110>750)$pdf->heading($invoice,false);
      $pdf->y+=30;$pdf->text(35,$pdf->y,'Verdeling van dit factuurbedrag',10,true);$pdf->y+=18;
      $pdf->text(35,$pdf->y,'Arbeid excl. btw: '.self::money((int)$invoice['labor_cents']).' - G-rekening '.OfficeSalesBilling::percentText((int)$invoice['g_rate_units']).'%',9);$pdf->y+=18;
      $pdf->text(35,$pdf->y,'Naar G-rekening: '.self::money((int)$invoice['g_cents']).' - '.($invoice['g_iban']!==''?$invoice['g_iban']:'rekeningnummer nog niet ingevuld'),9);$pdf->y+=18;
      $pdf->text(35,$pdf->y,'Naar gewone rekening: '.self::money((int)$invoice['gross_cents']-(int)$invoice['g_cents']).' - NL19 ABNA 0894 3515 67',9);
    }
    if((int)($invoice['source_term_id']??0)>0&&$invoice['summary_json']!==null){
      $summary=json_decode($invoice['summary_json'],true,32,JSON_THROW_ON_ERROR);$complete=(bool)$summary['coverage_complete'];
      if($pdf->y+195>750)$pdf->heading($invoice,false);
      $pdf->y+=30;$pdf->text(35,$pdf->y,'Aanneemsom en termijnen',10,true);$pdf->y+=20;
      $values=[['Aanneemsom excl. btw',self::money((int)$summary['contract_net_cents'])],['Deze termijn'.($invoice['term_percent_units']===null?'':' ('.OfficeSalesBilling::percentText((int)$invoice['term_percent_units'],4).'%)').' excl. btw',self::money((int)$invoice['net_cents'])],['Eerder gefactureerd excl. btw',self::money((int)$summary['previous_net_cents'])],['Geregistreerd betaald incl. btw',self::money((int)$summary['paid_gross_cents'])],['Openstaand incl. deze termijn, incl. btw',$complete?self::money((int)$summary['due_including_current_gross_cents']):'Niet volledig beschikbaar'],['Nog te factureren na deze termijn, excl. btw',$complete?self::money((int)$summary['unbilled_after_net_cents']):'Niet volledig beschikbaar']];
      foreach($values as [$label,$value]){$pdf->text(35,$pdf->y,$label,9);$pdf->text(0,$pdf->y,$value,9,false,553);$pdf->y+=18;}
      $pdf->text(35,$pdf->y,'Stand van gekoppelde facturen en geregistreerde ontvangsten bij opslaan.',8);
    }
    if(($invoice['invoice_number']??'')!==''){
      if($pdf->y+100>750)$pdf->heading($invoice,false);
      $pdf->y+=45;$pdf->text(35,$pdf->y,'Afsluiting:',10,true);$pdf->y+=19;
      $closing=(int)$invoice['g_cents']>0?'Betaal '.self::money((int)$invoice['g_cents']).' naar G-rekening '.$invoice['g_iban'].' en '.self::money((int)$invoice['gross_cents']-(int)$invoice['g_cents']).' naar NL19 ABNA 0894 3515 67, voor de vervaldatum onder vermelding van factuurnummer '.$invoice['invoice_number']:'Wij verzoeken u het bedrag voor vervaldatum van deze factuur over te maken op rekeningnummer NL19 ABNA 0894 3515 67 onder vermelding van factuurnummer '.$invoice['invoice_number'];
      foreach(self::wrap($closing,520,9) as $line){$pdf->text(35,$pdf->y,$line,9);$pdf->y+=15;}
    }
    $pdf->pages[]=$pdf->page;
    $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>''];
    foreach(self::fonts() as $key=>$font){
      $id=$key==='regular'?3:4;$descriptor=$id+3;$fileId=$id+5;$name=$key==='regular'?'BREBOA+DejaVuSans':'BREBOB+DejaVuSans-Bold';
      $widths=[];for($i=32;$i<=255;$i++)$widths[]=$font['widths'][$i]??556;
      $objects[$id]='<< /Type /Font /Subtype /TrueType /BaseFont /'.$name.' /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 255 /Widths ['.implode(' ',$widths).'] /FontDescriptor '.$descriptor.' 0 R >>';
      $objects[$descriptor]='<< /Type /FontDescriptor /FontName /'.$name.' /Flags 32 /FontBBox ['.implode(' ',$font['bbox']).'] /ItalicAngle 0 /Ascent '.$font['ascent'].' /Descent '.$font['descent'].' /CapHeight '.$font['ascent'].' /StemV 80 /FontFile2 '.$fileId.' 0 R >>';
      $fontBytes=base64_decode($font['data'],true);if($fontBytes===false)throw new \RuntimeException('Invalid font');
      $objects[$fileId]='<< /Length '.strlen($fontBytes).' /Length1 '.$font['length']." /Filter /FlateDecode >>\nstream\n".$fontBytes."\nendstream";
    }
    $logo=json_decode(file_get_contents(__DIR__.'/brebo-logo.rgb.json'),true,8,JSON_THROW_ON_ERROR);
    $image=base64_decode($logo['data'],true);if($image===false)throw new \RuntimeException('Invalid logo');
    $objects[5]='<< /Type /XObject /Subtype /Image /Width '.(int)$logo['width'].' /Height '.(int)$logo['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length '.strlen($image)." >>\nstream\n".$image."\nendstream";
    $kids=[];$count=count($pdf->pages);
    foreach($pdf->pages as $index=>$content){
      $pdf->page='';$pdf->text(159,796,'Op onze facturen zijn onze algemene voorwaarden van toepassing.',8);$pdf->logo(496,805,65);
      if($count>1)$pdf->text(35,817,($index+1).' / '.$count,8);$content.=$pdf->page;
      $pageId=10+$index*2;$streamId=$pageId+1;$kids[]=$pageId.' 0 R';
      $objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /XObject << /Logo 5 0 R >> >> /Contents '.$streamId.' 0 R >>';
      $objects[$streamId]='<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream';
    }
    $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.$count.' >>';
    ksort($objects);$out="%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";$offsets=[0];
    foreach($objects as $id=>$object){$offsets[$id]=strlen($out);$out.=$id." 0 obj\n".$object."\nendobj\n";}
    $xref=strlen($out);$out.='xref' ."\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
    for($id=1;$id<=count($objects);$id++)$out.=sprintf("%010d 00000 n \n",$offsets[$id]);
    return $out.'trailer << /Size '.(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
  }
}
