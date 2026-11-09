<?php
declare(strict_types=1);
namespace Brebo\Office\Sales;
/** Money and allocation rules owned by Office. All amounts use integer cents. */
final class OfficeSalesBilling {
  /** Calendar days, independent of daylight-saving transitions. */
  public static function dueDate(string $invoiceDate,mixed $days): string {
    $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$invoiceDate,new \DateTimeZone('UTC'));
    if(!$date||$date->format('Y-m-d')!==$invoiceDate)throw new \InvalidArgumentException('Ongeldige factuurdatum.');
    if((!is_string($days)&&!is_int($days))||!preg_match('/^\d{1,4}$/D',(string)$days)||(int)$days>3650)throw new \InvalidArgumentException('Vul een betalingstermijn van 0 tot en met 3650 hele dagen in.');
    $due=$date->modify('+'.(int)$days.' days')->format('Y-m-d');
    if(strlen($due)!==10)throw new \InvalidArgumentException('Vervaldatum ligt buiten het geldige datumbereik.');
    return $due;
  }
  /** Separate document metadata; the customer's reference never contains inferred project data. */
  public static function documentContext(array $invoice): array {
    $source=empty($invoice['source_snapshot'])?[]:json_decode($invoice['source_snapshot'],true,32,JSON_THROW_ON_ERROR);
    $customer=empty($invoice['customer_json'])?[]:json_decode($invoice['customer_json'],true,32,JSON_THROW_ON_ERROR);
    return ['project'=>(string)($source['project_title']??$customer['_office_project']['title']??''),'contract'=>(string)($source['contract_number']??'')];
  }
  public static function schema(bool $sqlite=false): string {
    return "CREATE TABLE office_finance_sales_context (
      invoice_id BIGINT NOT NULL PRIMARY KEY,
      price_mode VARCHAR(8) NOT NULL DEFAULT 'excl',
      labor_cents BIGINT NOT NULL DEFAULT 0,
      g_rate_units INTEGER NOT NULL DEFAULT 0,
      g_cents BIGINT NOT NULL DEFAULT 0,
      g_iban VARCHAR(34) NOT NULL DEFAULT '',
      source_contract_id BIGINT NULL,
      source_term_id BIGINT NULL UNIQUE,
      term_percent_units INTEGER NULL,
      source_snapshot TEXT NULL,
      summary_json TEXT NULL
    )".($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  }
  public static function defaults(array $row): array {
    foreach(['price_mode'=>'excl','labor_cents'=>0,'g_rate_units'=>0,'g_cents'=>0,'g_iban'=>'','source_contract_id'=>null,'source_term_id'=>null,'term_percent_units'=>null,'source_snapshot'=>null,'summary_json'=>null] as $key=>$default)if(!isset($row[$key]))$row[$key]=$default;
    return $row;
  }
  public static function cents(string $value,int $digits=10): int {
    $value=str_replace(',','.',trim($value));
    if(!preg_match('/^\d{1,'.$digits.'}(?:\.\d{1,2})?$/D',$value))throw new \InvalidArgumentException('Vul een geldig bedrag in.');
    [$a,$b]=array_pad(explode('.',$value),2,'');return (int)$a*100+(int)str_pad($b,2,'0');
  }
  public static function decimal(int $cents): string {return intdiv($cents,100).'.'.sprintf('%02d',$cents%100);}
  public static function percent(string $value,int $places=2): int {
    $value=str_replace(',','.',trim($value));
    if(!preg_match('/^\d{1,3}(?:\.\d{1,'.$places.'})?$/D',$value))throw new \InvalidArgumentException('Vul een percentage van 0 tot en met 100 in.');
    [$a,$b]=array_pad(explode('.',$value),2,'');$units=(int)$a*(10**$places)+(int)str_pad($b,$places,'0');
    if($units>100*(10**$places))throw new \InvalidArgumentException('Percentage is hoger dan 100.');return $units;
  }
  public static function percentText(int $units,int $places=2): string {return rtrim(rtrim(number_format($units/(10**$places),$places,'.',''),'0'),'.');}
  public static function totals(array $lines,string $mode='excl'): array {
    if(!in_array($mode,['excl','incl'],true))throw new \InvalidArgumentException('Kies invoer inclusief of exclusief btw.');
    if($lines===[]||count($lines)>100)throw new \InvalidArgumentException('Vul 1 tot 100 factuurregels in.');
    $net=0;$vat=0;$clean=[];
    foreach($lines as $line){
      $description=trim((string)($line['description']??''));$quantity=str_replace(',','.',(string)($line['quantity']??''));$price=str_replace(',','.',(string)($line['price']??''));$rate=(string)($line['vat']??'');
      if($description===''||strlen($description)>1000||preg_match('/[\x00-\x1f]/',$description)||!preg_match('/^\d{1,6}(?:\.\d{1,3})?$/D',$quantity)||!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D',$price)||!in_array($rate,['0','9','21'],true))throw new \InvalidArgumentException('Ongeldige factuurregel.');
      [$a,$b]=array_pad(explode('.',$quantity),2,'');$units=(int)$a*1000+(int)str_pad($b,3,'0');if($units<=0)throw new \InvalidArgumentException('Aantal moet positief zijn.');
      $cents=self::cents($price,7);$amount=intdiv($units*$cents+500,1000);
      if($mode==='incl'){$gross=$amount;$denominator=100+(int)$rate;$amount=intdiv($gross*100+intdiv($denominator,2),$denominator);$tax=$gross-$amount;}else{$tax=intdiv($amount*(int)$rate+50,100);$gross=$amount+$tax;}
      $net+=$amount;$vat+=$tax;if($net+$vat>999999999999)throw new \InvalidArgumentException('Factuurtotaal te groot.');
      $clean[]=['description'=>$description,'quantity'=>$quantity,'price'=>$price,'vat'=>$rate,'net_cents'=>$amount,'vat_cents'=>$tax,'gross_cents'=>$gross];
    }
    return ['lines'=>$clean,'net_cents'=>$net,'vat_cents'=>$vat,'gross_cents'=>$net+$vat];
  }
  public static function taxes(array $lines): array {
    $taxes=[];foreach($lines as $line){$rate=(string)$line['vat'];$taxes[$rate]??=['net_cents'=>0,'vat_cents'=>0];$taxes[$rate]['net_cents']+=(int)$line['net_cents'];$taxes[$rate]['vat_cents']+=(int)$line['vat_cents'];}krsort($taxes,SORT_NUMERIC);return $taxes;
  }
  public static function validGiban(string $iban): bool {
    if(!preg_match('/^NL\d{2}[A-Z]{4}\d{10}$/D',$iban)||$iban==='NL19ABNA0894351567')return false;
    $text=substr($iban,4).substr($iban,0,4);$digits='';foreach(str_split($text) as $char)$digits.=ctype_alpha($char)?(string)(ord($char)-55):$char;
    $remainder=0;foreach(str_split($digits) as $digit)$remainder=($remainder*10+(int)$digit)%97;return $remainder===1;
  }
  public static function allocation(array $input,int $net,int $gross): array {
    $labor=trim((string)($input['labor_amount']??''));$labor=$labor===''?0:self::cents($labor);
    $percent=trim((string)($input['g_percent']??''));$rate=$percent===''?0:self::percent($percent);
    $iban=strtoupper(preg_replace('/\s+/','',(string)($input['g_iban']??''))??'');
    if($labor>$net)throw new \InvalidArgumentException('De arbeidsprijs exclusief btw is hoger dan het factuurbedrag exclusief btw.');
    if($rate>0&&$labor<=0)throw new \InvalidArgumentException('Bepaal eerst de arbeidsprijs exclusief btw voor de G-rekening.');
    if($iban!==''&&!self::validGiban($iban))throw new \InvalidArgumentException('Controleer het Nederlandse G-rekeningnummer.');
    $g=intdiv($labor*$rate+5000,10000);if($rate>0&&$g<=0)throw new \InvalidArgumentException('Het G-rekeningdeel is kleiner dan één cent.');
    return ['labor_cents'=>$labor,'g_rate_units'=>$rate,'g_cents'=>$g,'g_iban'=>$iban,'ordinary_cents'=>$gross-$g];
  }
  public static function termAmount(array $term,string $percentage): array {
    $contract=self::cents((string)$term['contract_amount']);$planned=self::cents((string)$term['amount']);$units=trim($percentage)===''?null:self::percent($percentage,4);
    if($contract<=0||$contract>999999999999||$planned<=0)throw new \DomainException('Deze termijn heeft geen bruikbare aanneemsom.');
    $amount=$units===null?$planned:intdiv($contract*$units+500000,1000000);
    if($amount<=0||$amount>$contract||$amount>999999999)throw new \InvalidArgumentException('Het termijnpercentage past niet bij de aanneemsom.');
    return ['net_cents'=>$amount,'term_percent_units'=>$units];
  }
}
