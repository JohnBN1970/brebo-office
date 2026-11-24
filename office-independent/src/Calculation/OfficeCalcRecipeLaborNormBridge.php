<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;
use DomainException;
require_once __DIR__.'/OfficeCalcTypeQuantities.php';
require_once __DIR__.'/OfficeCalcProductivityNorm.php';

/** One physical type feeds independent labor recipes; display unit does not change hours. */
final class OfficeCalcRecipeLaborNormBridge
{
    public static function preview(array $type,array $housingTypes,array $activities):array
    {
        $measurements=OfficeCalcTypeQuantities::calculate($type,$housingTypes);
        $result=[];$seen=[];
        foreach($activities as $activity){
            $code=$activity['code']??null;
            if(!is_string($code)||!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D',$code)||isset($seen[$code]))
                throw new DomainException('Ongeldig of dubbel recept.');
            $seen[$code]=true;
            $basis=$activity['basis_unit']??null;
            $display=$activity['display_unit']??$basis;
            $norm=$activity['norm']??null;
            if(!is_array($norm)||($norm['unit']??null)!==$basis)
                throw new DomainException('Receptnorm en meetbasis komen niet overeen.');
            $result[]=['code'=>$code,'calculation'=>OfficeCalcProductivityNorm::convert($measurements,$norm,$display)];
        }
        return ['type'=>$measurements,'activities'=>$result];
    }
}
