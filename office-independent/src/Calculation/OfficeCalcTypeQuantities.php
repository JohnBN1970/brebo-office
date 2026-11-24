<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;
use DomainException;

/** Calculates a shared window/glass type across housing types, with exact BCMath. */
final class OfficeCalcTypeQuantities
{
    public static function calculate(array $type, array $housingTypes): array
    {
        if(!extension_loaded('bcmath'))throw new DomainException('BCMath vereist.');
        $code=$type['code']??null;
        if(!is_string($code)||!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D',$code)
            ||!in_array($type['discipline']??null,['kozijnen','glas'],true))
            throw new DomainException('Ongeldig kozijn- of glastype.');
        $width=self::positive($type['width_m']??null);
        $height=self::positive($type['height_m']??null);
        $perimeter=bcadd(bcmul('2',$width,4),bcmul('2',$height,4),4);
        $area=bcmul($width,$height,4);
        if($housingTypes===[])throw new DomainException('Geen woningtypen gekoppeld.');
        $rows=[];$seen=[];$total='0.0000';
        foreach($housingTypes as $housing){
            $id=$housing['housing_type_id']??null;
            if(!is_int($id)||$id<1||isset($seen[$id]))throw new DomainException('Ongeldig of dubbel woningtype.');
            $seen[$id]=true;
            $homes=self::whole($housing['homes']??null);
            $perHome=self::whole($housing['items_per_home']??null,true);
            $count=bcmul($homes,$perHome,0);
            $total=bcadd($total,$count,4);
            $rows[]=['housing_type_id'=>$id,'homes'=>$homes,'items_per_home'=>$perHome,
                'items_total'=>$count,'perimeter_m'=>bcmul($perimeter,$count,4),
                'area_m2'=>bcmul($area,$count,4)];
        }
        return ['type_code'=>$code,'discipline'=>$type['discipline'],
            'width_m'=>$width,'height_m'=>$height,
            'perimeter_per_item_m'=>$perimeter,'area_per_item_m2'=>$area,
            'items_total'=>$total,'perimeter_total_m'=>bcmul($perimeter,$total,4),
            'area_total_m2'=>bcmul($area,$total,4),'housing_types'=>$rows];
    }
    private static function positive(mixed $value):string
    {
        if(!is_string($value)||!preg_match('/^(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,4})?$/D',$value)
            ||bccomp($value,'0',4)<=0)throw new DomainException('Ongeldige afmeting.');
        return bcadd($value,'0',4);
    }
    private static function whole(mixed $value,bool $zero=false):string
    {
        if(!is_int($value)||$value<($zero?0:1)||$value>1000000)throw new DomainException('Ongeldig woningaantal of aantal per woning.');
        return (string)$value;
    }
}
