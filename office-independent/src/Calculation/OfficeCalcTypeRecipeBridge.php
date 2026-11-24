<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;
use DomainException;
require_once __DIR__.'/OfficeCalcTypeQuantities.php';
require_once __DIR__.'/OfficeCalcRecipeHierarchy.php';

/** Read-only bridge from canonical housing-type counts to versioned recipes. */
final class OfficeCalcTypeRecipeBridge
{
    public static function preview(array $type,array $housingTypes,array $chapter,array $selections,string $buildingSource):array
    {
        if(!preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D',$buildingSource))
            throw new DomainException('Ongeldige gebouwbron.');
        $totals=OfficeCalcTypeQuantities::calculate($type,$housingTypes);
        $unit=$chapter['measurement_unit']??null;
        $quantity=match($unit){
            'st'=>$totals['items_total'],
            'm'=>$totals['perimeter_total_m'],
            'm2'=>$totals['area_total_m2'],
            default=>throw new DomainException('Recept moet expliciet stuks, perimeter of oppervlak gebruiken.')
        };
        $chapterPreview=OfficeCalcRecipeHierarchy::preview($chapter,$selections,$quantity,$unit,
            $buildingSource.':'.$totals['type_code']);
        return ['type'=>$totals,'measurement_unit'=>$unit,'measurement_quantity'=>$quantity,
            'chapter'=>$chapterPreview];
    }
}
