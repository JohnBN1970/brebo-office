<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Calculation/OfficeCalcRecipeSnapshot.php';
use Brebo\Office\Calculation\OfficeCalcRecipeSnapshot;

$recipe = [
 'code'=>'kunststof-kozijn-montage','version'=>2,'unit'=>'m2',
 'lines'=>[
  ['code'=>'montage','description'=>'Montage kunststof kozijnen','cost_type'=>'labor','unit'=>'uur','per_unit'=>'1.2500','unit_cost'=>null,'price_source'=>null],
  ['code'=>'kit','description'=>'Kitwerk','cost_type'=>'material','unit'=>'m','per_unit'=>'2.0000','unit_cost'=>'3.2500','price_source'=>['supplier'=>'test']],
 ]
];
$result=OfficeCalcRecipeSnapshot::expand($recipe,'300.0000','m2','building_element:1:2');
assert($result['recipe_version']===2);
if($result['lines'][0]['quantity']!=='375.0000'||$result['lines'][1]['quantity']!=='600.0000'
 ||$result['lines'][0]['pricing_status']!=='requires_price'
 ||$result['lines'][1]['unit_cost']!=='3.2500')exit(1);
try {OfficeCalcRecipeSnapshot::expand($recipe,'300.0000','st','building_element:1:2');exit(1);}catch(DomainException){}
$recipe['lines'][1]['code']='montage';
try {OfficeCalcRecipeSnapshot::expand($recipe,'300.0000','m2','building_element:1:2');exit(1);}catch(DomainException){}
echo "RECIPE_SNAPSHOT_CONTRACT=OK\n";
