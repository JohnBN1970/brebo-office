<?php
declare(strict_types=1);
namespace Brebo\Office\Finance\Center;
final class Amounts {
  public static function cents(mixed $input,bool $signed=false): int {
    if(!is_string($input)&&!is_int($input))throw new \InvalidArgumentException('Vul een geldig bedrag in.');$s=str_replace(',','.',trim((string)$input));
    if(!preg_match($signed?'/^-?\d{1,10}(?:\.\d{1,2})?$/D':'/^\d{1,10}(?:\.\d{1,2})?$/D',$s))throw new \InvalidArgumentException('Bedrag: gebruik maximaal twee decimalen.');
    $negative=str_starts_with($s,'-');[$a,$b]=array_pad(explode('.',ltrim($s,'-')),2,'');$n=(int)$a*100+(int)str_pad($b,2,'0');return $negative?-$n:$n;
  }
  public static function date(mixed $input): string {
    if(!is_string($input))throw new \InvalidArgumentException('Ongeldige datum.');$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$input,new \DateTimeZone('UTC'));if(!$d||$d->format('Y-m-d')!==$input)throw new \InvalidArgumentException('Ongeldige datum.');return $input;
  }
  public static function text(mixed $input,int $max=255): string {if(!is_string($input)||trim($input)===''||strlen(trim($input))>$max||preg_match('/[\x00-\x1f]/',$input))throw new \InvalidArgumentException('Vul een geldige omschrijving of bronverwijzing in.');return trim($input);}
  public static function id(mixed $v): int{$id=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!is_int($id))throw new \InvalidArgumentException('Ongeldig nummer.');return $id;}
  public static function money(int $n): string{$s=$n<0?'-':'';$n=abs($n);return $s.'€ '.number_format(intdiv($n,100),0,',','.').','.sprintf('%02d',$n%100);}
  public static function decimal(int $n): string{return ($n<0?'-':'').intdiv(abs($n),100).'.'.sprintf('%02d',abs($n)%100);}
  public static function hash(mixed $v): string{return hash('sha256',json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
}
