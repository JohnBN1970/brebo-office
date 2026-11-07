<?php
declare(strict_types=1);
namespace Brebo\Office\Sales;

/** Local PDF export of the authorized, saved Office invoice; no provider calls. */
final class OfficeSalesPdf {
  private array $pages=[];
  private string $page='';
  private float $y=0;
  private const WIDTHS=[32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,64=>1015,65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>278,92=>278,93=>278,94=>469,95=>556,96=>333,97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,111=>556,112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584,127=>761,128=>556,130=>222,131=>556,132=>333,133=>1000,134=>556,135=>556,136=>333,137=>1000,138=>667,139=>333,140=>1000,142=>611,145=>222,146=>222,147=>333,148=>333,149=>350,150=>556,151=>1000,152=>333,153=>1000,154=>500,155=>333,156=>944,158=>500,159=>667,160=>278,161=>333,162=>556,163=>556,164=>556,165=>556,166=>260,167=>556,168=>333,169=>737,170=>370,171=>556,172=>584,173=>333,174=>737,175=>333,176=>400,177=>584,178=>333,179=>333,180=>333,181=>556,182=>537,183=>278,184=>333,185=>333,186=>365,187=>556,188=>834,189=>834,190=>834,191=>611,192=>667,193=>667,194=>667,195=>667,196=>667,197=>667,198=>1000,199=>722,200=>667,201=>667,202=>667,203=>667,204=>278,205=>278,206=>278,207=>278,208=>722,209=>722,210=>778,211=>778,212=>778,213=>778,214=>778,215=>584,216=>778,217=>722,218=>722,219=>722,220=>722,221=>667,222=>667,223=>611,224=>556,225=>556,226=>556,227=>556,228=>556,229=>556,230=>889,231=>500,232=>556,233=>556,234=>556,235=>556,236=>278,237=>278,238=>278,239=>278,240=>556,241=>556,242=>556,243=>556,244=>556,245=>556,246=>556,247=>584,248=>611,249=>556,250=>556,251=>556,252=>556,253=>500,254=>556,255=>500];
  private static function encoded(string $text): string {
    $text=preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u','',$text)??'';
    $bytes=iconv('UTF-8','Windows-1252//TRANSLIT',$text);
    if($bytes===false)throw new \RuntimeException('PDF text encoding unavailable');
    return $bytes;
  }
  private static function width(string $bytes,float $size): float {
    $width=0;foreach(str_split($bytes) as $char)$width+=self::WIDTHS[ord($char)]??556;
    return $width*$size/1000;
  }
  private function text(float $x,float $y,string $text,float $size=10,bool $bold=false,?float $right=null): void {
    $bytes=self::encoded($text);
    if($right!==null)$x=$right-self::width($bytes,$size);
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
  private function heading(array $invoice,bool $first): void {
    if($this->page!=='')$this->pages[]=$this->page;
    $this->page="0.12 g\n";
    $draft=($invoice['invoice_number']??'')==='';
    $this->text(42,44,'BREBO',22,true);
    $this->text(42,62,'Bouw en Advies B.V.',10);
    $this->text(42,78,'Plaza 25A - 4782 SL Moerdijk',9);
    $this->text(355,46,$draft?'CONCEPTFACTUUR':'FACTUUR',17,true);
    $this->text(355,65,$draft?'Concept #'.$invoice['id']:(string)$invoice['invoice_number'],11);
    if($first) {
      $customer=json_decode($invoice['customer_json'],true,32,JSON_THROW_ON_ERROR);
      $address=array_filter([(string)($customer['legal_name']?:$customer['display_name']),(string)$customer['street'],trim($customer['postal_code'].' '.$customer['city']),(string)($customer['country_code']??'')]);
      $ay=119;
      foreach($address as $part)foreach(self::wrap($part,280) as $line){$this->text(42,$ay,$line);$ay+=13;}
      $this->text(355,119,'Factuurdatum: '.$invoice['invoice_date']);
      $this->text(355,137,'Vervaldatum: '.$invoice['due_date']);
      $this->y=max(192,$ay+18);
      if(($invoice['reference']??'')!=='')foreach(self::wrap('Referentie: '.$invoice['reference'],505) as $line){$this->text(42,$this->y,$line);$this->y+=13;}
      if($draft){$this->text(42,$this->y+8,'Concept - nog niet verzonden. Geen betaalverzoek.',10,true);$this->y+=27;}
    } else {$this->y=105;}
    $this->tableHead();
  }
  private function tableHead(): void {
    $this->rule($this->y);$this->y+=18;
    foreach([[42,'Omschrijving'],[331,'Aantal'],[393,'Prijs'],[456,'Btw'],[503,'Bedrag']] as [$x,$label])$this->text($x,$this->y,$label,9,true);
    $this->y+=10;$this->rule($this->y);$this->y+=19;
  }
  private static function money(int $cents): string {return '€ '.number_format($cents/100,2,',','.');}
  public static function render(array $invoice): string {
    $pdf=new self();$pdf->heading($invoice,true);
    $lines=json_decode($invoice['lines_json'],true,32,JSON_THROW_ON_ERROR);$tax=[];
    foreach($lines as $line) {
      $wrapped=self::wrap((string)$line['description'],274);
      if($pdf->y+count($wrapped)*13+12>735)$pdf->heading($invoice,false);
      $first=true;
      foreach($wrapped as $description) {
        if($pdf->y>735)$pdf->heading($invoice,false);
        $pdf->text(42,$pdf->y,$description);
        if($first){
          $pdf->text(331,$pdf->y,(string)$line['quantity'],9);
          $price=(string)$line['price'];[$euros,$fraction]=array_pad(explode('.',$price),2,'');
          $priceCents=(int)$euros*100+(int)str_pad($fraction,2,'0');
          $pdf->text(0,$pdf->y,self::money($priceCents),9,false,445);
          $pdf->text(456,$pdf->y,$line['vat'].'%',9);
          $pdf->text(0,$pdf->y,self::money((int)$line['net_cents']),9,false,553);$first=false;
        }
        $pdf->y+=13;
      }
      $pdf->y+=10;$pdf->rule($pdf->y-4);
      $rate=(string)$line['vat'];$tax[$rate]=($tax[$rate]??0)+(int)$line['vat_cents'];
    }
    if($pdf->y+90+count($tax)*20>750)$pdf->heading($invoice,false);
    $pdf->y+=21;
    $pdf->text(340,$pdf->y,'Subtotaal excl. btw');$pdf->text(0,$pdf->y,self::money((int)$invoice['net_cents']),10,false,553);
    foreach($tax as $rate=>$amount){$pdf->y+=20;$pdf->text(340,$pdf->y,'Btw '.$rate.'%');$pdf->text(0,$pdf->y,self::money($amount),10,false,553);}
    $pdf->y+=25;$pdf->text(340,$pdf->y,'Totaal incl. btw',11,true);$pdf->text(0,$pdf->y,self::money((int)$invoice['gross_cents']),11,false,553);
    $pdf->pages[]=$pdf->page;
    $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];$kids=[];$count=count($pdf->pages);
    foreach($pdf->pages as $index=>$content){
      $pdf->page='';$pdf->text(42,806,'BREBO Office - '.(($invoice['invoice_number']??'')===''?'Concept':'Administratieve afdruk'),8);
      $pdf->text(0,806,($index+1).' / '.$count,8,false,553);$content.=$pdf->page;
      $pageId=5+$index*2;$streamId=$pageId+1;$kids[]=$pageId.' 0 R';
      $objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$streamId.' 0 R >>';
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
