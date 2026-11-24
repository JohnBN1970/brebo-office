<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;
use DomainException;

/** Immutable labor norm conversion across quantities derived from one physical type. */
final class OfficeCalcProductivityNorm
{
    public static function convert(array $measurements, array $norm, string $displayUnit): array
    {
        if(!extension_loaded('bcmath'))throw new DomainException('BCMath vereist.');
        $sourceUnit=$norm['unit']??null;
        if(!in_array($sourceUnit,['st','m','m2'],true)||!in_array($displayUnit,['st','m','m2'],true))
            throw new DomainException('Ongeldige eenheid.');
        $units=['st'=>'items_total','m'=>'perimeter_total_m','m2'=>'area_total_m2'];
        $source=self::positive($measurements[$units[$sourceUnit]]??null);
        $target=self::positive($measurements[$units[$displayUnit]]??null);
        $perDay=self::positive($norm['quantity_per_day']??null);
        $hoursPerDay=self::positive($norm['hours_per_day']??null);
        $crew=self::whole($norm['crew_size']??null);
        // Days and labor-hours stay unchanged when only the display unit changes.
        $days=bcdiv($source,$perDay,8);
        $laborHours=bcmul(bcmul($days,$hoursPerDay,8),$crew,8);
        return [
            'source_norm'=>['unit'=>$sourceUnit,'quantity_per_day'=>$perDay,
                'hours_per_day'=>$hoursPerDay,'crew_size'=>$crew],
            'display_unit'=>$displayUnit,'display_quantity'=>$target,
            'quantity_per_day'=>bcdiv($target,$days,8),
            'labor_hours_per_unit'=>bcdiv($laborHours,$target,8),
            'planned_crew_days'=>$days,'planned_labor_hours'=>$laborHours
        ];
    }
    private static function positive(mixed $value):string
    {
        if(!is_string($value)||!preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,8})?$/D',$value)
            ||bccomp($value,'0',8)<=0)throw new DomainException('Ongeldige norm of hoeveelheid.');
        return $value;
    }
    private static function whole(mixed $value):string
    {
        if(!is_int($value)||$value<1||$value>1000)throw new DomainException('Ongeldige ploegbezetting.');
        return (string)$value;
    }
}
