<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;
use DomainException;
require_once __DIR__.'/OfficeCalcRecipeChoices.php';
require_once __DIR__.'/OfficeCalcRecipeSnapshot.php';
/** Read-only hierarchy preview; alternatives never enter baseline. */
final class OfficeCalcRecipeHierarchy
{
    public static function preview(array $chapter, array $selections, string $quantity, string $unit, string $source): array
    {
        if (!is_string($chapter['code'] ?? null) || !preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $chapter['code'])
            || !is_string($chapter['title'] ?? null) || trim($chapter['title']) === ''
            || !is_array($chapter['sections'] ?? null)) throw new DomainException('Ongeldig calculatiesubhoofdstuk.');
        $resolved=OfficeCalcRecipeChoices::resolve($chapter['sections'],$selections);
        $sections=[];
        foreach($resolved as $choice){
            $baseline=$choice['baseline']===null?null:self::expandOption($choice['baseline'],$quantity,$unit,$source);
            $alternatives=[];
            foreach($choice['alternatives'] as $option)$alternatives[]=self::expandOption($option,$quantity,$unit,$source);
            $sections[]=['code'=>$choice['code'],'enabled'=>$choice['enabled'],'baseline'=>$baseline,
                'alternatives'=>$alternatives,'available_options'=>$choice['available_options']];
        }
        return ['chapter_code'=>$chapter['code'],'chapter_title'=>$chapter['title'],
            'building_source_ref'=>$source,'sections'=>$sections];
    }
    private static function expandOption(array $option,string $quantity,string $unit,string $source): array
    {
        $snapshot=OfficeCalcRecipeSnapshot::expand($option['recipe'],$quantity,$unit,$source);
        return ['option_code'=>$option['code'],'recipe'=>$snapshot,
            'is_complete'=>!in_array('requires_price',array_column($snapshot['lines'],'pricing_status'),true)];
    }
}
