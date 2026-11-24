<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Calculation/OfficeCalcRecipeLaborNormBridge.php';
use Brebo\Office\Calculation\OfficeCalcRecipeLaborNormBridge;
$type=['code'=>'K01','discipline'=>'kozijnen','width_m'=>'1.2000','height_m'=>'1.5000'];
$housing=[['housing_type_id'=>1,'homes'=>12,'items_per_home'=>3]];
$activities=[
 ['code'=>'montage','basis_unit'=>'st','display_unit'=>'m2','norm'=>[
  'unit'=>'st','quantity_per_day'=>'2','hours_per_day'=>'8','crew_size'=>2]],
 ['code'=>'stelkozijnen','basis_unit'=>'m','display_unit'=>'st','norm'=>[
  'unit'=>'m','quantity_per_day'=>'30','hours_per_day'=>'8','crew_size'=>2]],
 ['code'=>'kantelaaf','basis_unit'=>'m','display_unit'=>'m2','norm'=>[
  'unit'=>'m','quantity_per_day'=>'20','hours_per_day'=>'8','crew_size'=>1]]
];
$result=OfficeCalcRecipeLaborNormBridge::preview($type,$housing,$activities);
if($result['type']['perimeter_total_m']!=='194.4000'
 ||$result['activities'][0]['calculation']['display_quantity']!=='64.8000'
 ||$result['activities'][0]['calculation']['planned_labor_hours']!=='288.00000000'
 ||$result['activities'][1]['calculation']['planned_labor_hours']!=='103.68000000'
 ||$result['activities'][2]['calculation']['planned_labor_hours']!=='77.76000000')exit(1);
$activities[1]['norm']['unit']='st';
try{OfficeCalcRecipeLaborNormBridge::preview($type,$housing,$activities);exit(1);}catch(DomainException $e){}
echo "CALC_RECIPE_LABOR_NORMS=OK\n";
