<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

/** Read-only sum of individual labor hours, never crew days or money. */
final class OfficeCalcProjectLaborTotal
{
    public static function summarize(array $lines): array
    {
        $hours='0.0000';
        $unknown=0;
        $count=0;
        foreach($lines as $line){
            if(!is_array($line))throw new \DomainException('Ongeldige calculatieregel.');
            $value=$line['labor_hours']??null;
            $isLabor=($line['cost_type']??'')==='labor'
                || (isset($line['cost_components']) && bccomp((string)($line['cost_components']['labor']??'0'),'0',4)>0);
            if(!$isLabor)continue;
            ++$count;
            if($value===null||$value===''){$unknown++;continue;}
            $value=(string)$value;
            if(!preg_match('/^(?:0|[1-9][0-9]{0,8})(?:\\.[0-9]{1,4})?$/D',$value))
                throw new \DomainException('Ongeldige arbeidsuren.');
            $hours=bcadd($hours,$value,4);
        }
        return ['hours'=>$hours,'labor_lines'=>$count,'unknown_lines'=>$unknown,'complete'=>$unknown===0];
    }
}
