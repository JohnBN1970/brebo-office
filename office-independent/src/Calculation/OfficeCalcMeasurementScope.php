<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;
use DomainException;

/**
 * Validates mixed measurement granularity without inventing individual objects.
 * Aggregate quantities are already totals for the declared scope.
 * Detailed positions are measured individually and summed once.
 */
final class OfficeCalcMeasurementScope
{
    public static function normalize(array $scope): array
    {
        $mode=$scope['mode']??null;
        if(!in_array($mode,['aggregate','detailed'],true))throw new DomainException('Onbekend meetniveau.');
        $type=$scope['discipline']??null;
        if(!is_string($type)||!preg_match('/^[a-z][a-z0-9_-]{1,63}$/D',$type))throw new DomainException('Ongeldige discipline.');
        $unit=$scope['unit']??null;
        if(!in_array($unit,['m','m2','m3','st','kg','uur'],true))throw new DomainException('Ongeldige meeteenheid.');
        $source=$scope['source_ref']??null;
        if(!is_string($source)||!preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D',$source))throw new DomainException('Ongeldige bron.');
        $quantity=null;$positions=[];
        if($mode==='aggregate'){
            if(array_key_exists('positions',$scope) && $scope['positions']!==[])throw new DomainException('Aggregaat mag geen posities bevatten.');
            $quantity=self::decimal($scope['quantity']??null);
        }else{
            if(array_key_exists('quantity',$scope))throw new DomainException('Detail mag geen tweede totaalhoeveelheid bevatten.');
            if(!is_array($scope['positions']??null)||$scope['positions']===[])throw new DomainException('Detailposities ontbreken.');
            $seen=[];$quantity='0.0000';
            foreach($scope['positions'] as $position){
                $id=$position['id']??null;
                if(!is_string($id)||!preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D',$id)||isset($seen[$id]))
                    throw new DomainException('Ongeldige of dubbele positie.');
                $seen[$id]=true;
                $amount=self::decimal($position['quantity']??null);
                $quantity=bcadd($quantity,$amount,4);
                $positions[]=['id'=>$id,'quantity'=>$amount];
            }
        }
        return ['mode'=>$mode,'discipline'=>$type,'unit'=>$unit,'source_ref'=>$source,
            'quantity'=>$quantity,'positions'=>$positions];
    }

    private static function decimal(mixed $value):string
    {
        if(!extension_loaded('bcmath'))throw new DomainException('BCMath vereist.');
        if(!is_string($value)||!preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,4})?$/D',$value)
            ||bccomp($value,'0',4)<=0)throw new DomainException('Ongeldige hoeveelheid.');
        return bcadd($value,'0',4);
    }
}
