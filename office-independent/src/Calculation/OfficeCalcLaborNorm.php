<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;

/** Decimal-safe, bidirectional labor norm calculations; no database side effects. */
final class OfficeCalcLaborNorm
{
    private static function number(string $value,bool $positive=false): string
    {
        if(!preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,4})?$/D',$value)
            || ($positive&&bccomp($value,'0',4)<=0))throw new DomainException('Ongeldige arbeidswaarde.');
        return $value;
    }

    /**
     * $driver: norm means hours per unit is authoritative; hours means total hours is authoritative.
     * Amounts and hours are rounded to four decimals, independently of display formatting.
     */
    public static function calculate(string $quantity,string $driver,string $input,string $hourlyRate): array
    {
        $quantity=self::number($quantity,true);
        $input=self::number($input);
        $hourlyRate=self::number($hourlyRate);
        if(!in_array($driver,['norm','hours'],true))throw new DomainException('Ongeldige arbeidsberekeningsrichting.');
        if($driver==='norm'){
            $norm=bcadd($input,'0',4);
            $hours=bcmul($quantity,$norm,4);
        }else{
            $hours=bcadd($input,'0',4);
            $norm=bcdiv($hours,$quantity,4);
        }
        $total=bcmul($hours,$hourlyRate,4);
        $unitCost=bcdiv($total,$quantity,4);
        return ['quantity'=>$quantity,'driver'=>$driver,'norm'=>$norm,'hours'=>$hours,
            'hourly_rate'=>bcadd($hourlyRate,'0',4),'labor_total'=>$total,'labor_unit_cost'=>$unitCost];
    }
}
