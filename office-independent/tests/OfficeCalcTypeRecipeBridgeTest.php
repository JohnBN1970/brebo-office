<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Calculation/OfficeCalcTypeRecipeBridge.php';
use Brebo\Office\Calculation\OfficeCalcTypeRecipeBridge;
$recipe=static fn($unit)=>['code'=>'kit','version'=>1,'unit'=>$unit,'lines'=>[
 ['code'=>'kitregel','description'=>'Afdichting','cost_type'=>'material','unit'=>'m',
  'per_unit'=>'1.0000','unit_cost'=>null,'price_source'=>null]]];
$chapter=['code'=>'31.01','title'=>'Kunststof kozijnen','measurement_unit'=>'m','sections'=>[
 ['code'=>'afdichting','options'=>[['code'=>'standaard','recipe'=>$recipe('m')]]]
]];
$selections=['afdichting'=>['enabled'=>true,'primary'=>'standaard','alternatives'=>[]]];
$result=OfficeCalcTypeRecipeBridge::preview(
 ['code'=>'K01','discipline'=>'kozijnen','width_m'=>'1.2000','height_m'=>'1.5000'],
 [['housing_type_id'=>1,'homes'=>12,'items_per_home'=>3]],$chapter,$selections,'building:1');
if($result['measurement_quantity']!=='194.4000'
 ||$result['chapter']['sections'][0]['baseline']['recipe']['lines'][0]['quantity']!=='194.4000'
 ||$result['chapter']['sections'][0]['baseline']['is_complete']!==false)exit(1);
$chapter['measurement_unit']='m2';
try{OfficeCalcTypeRecipeBridge::preview(
 ['code'=>'K01','discipline'=>'kozijnen','width_m'=>'1.2000','height_m'=>'1.5000'],
 [['housing_type_id'=>1,'homes'=>12,'items_per_home'=>3]],$chapter,$selections,'building:1');exit(1);
}catch(DomainException $e){}
echo "CALC_TYPE_RECIPE_BRIDGE=OK\n";
