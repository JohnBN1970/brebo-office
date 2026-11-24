<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Calculation/OfficeCalcRecipeHierarchy.php';
use Brebo\Office\Calculation\OfficeCalcRecipeHierarchy;
$recipe=static fn(string $code,string $cost):array=>[
 'code'=>$code,'version'=>1,'unit'=>'m2',
 'lines'=>[['code'=>'materiaal','description'=>'Materiaal','cost_type'=>'material',
  'unit'=>'st','per_unit'=>'2.0000','unit_cost'=>$cost,'price_source'=>null]]
];
$chapter=['code'=>'31.01','title'=>'Kunststof kozijnen','sections'=>[
 ['code'=>'stelkozijnen','options'=>[
  ['code'=>'hout','recipe'=>$recipe('stel-hout','5.0000')],
  ['code'=>'kunststof','recipe'=>$recipe('stel-kunststof','7.0000')]]],
 ['code'=>'vensterbanken','options'=>[
  ['code'=>'composiet','recipe'=>$recipe('vensterbank', '3.0000')]]]
]];
$result=OfficeCalcRecipeHierarchy::preview($chapter,[
 'stelkozijnen'=>['enabled'=>true,'primary'=>'hout','alternatives'=>['kunststof']],
 'vensterbanken'=>['enabled'=>false,'primary'=>null,'alternatives'=>['composiet']]
],'300.0000','m2','building_element:1:2');
if($result['chapter_title']!=='Kunststof kozijnen'
 ||$result['sections'][0]['baseline']['recipe']['lines'][0]['quantity']!=='600.0000'
 ||count($result['sections'][0]['alternatives'])!==1
 ||$result['sections'][0]['alternatives'][0]['recipe']['lines'][0]['unit_cost']!=='7.0000'
 ||$result['sections'][1]['baseline']!==null)exit(1);
echo "RECIPE_HIERARCHY_CONTRACT=OK\n";
