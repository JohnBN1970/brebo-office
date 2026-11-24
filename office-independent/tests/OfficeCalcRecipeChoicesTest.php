<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Calculation/OfficeCalcRecipeChoices.php';
use Brebo\Office\Calculation\OfficeCalcRecipeChoices;
$sections=[
 ['code'=>'stelkozijnen','options'=>[
  ['code'=>'hout','recipe'=>['version'=>1]],['code'=>'kunststof','recipe'=>['version'=>2]]
 ]],
 ['code'=>'vensterbanken','options'=>[
  ['code'=>'composiet','recipe'=>['version'=>1]],['code'=>'natuursteen','recipe'=>['version'=>1]]
 ]]
];
$choices=OfficeCalcRecipeChoices::resolve($sections,[
 'stelkozijnen'=>['enabled'=>true,'primary'=>'hout','alternatives'=>['kunststof']],
 'vensterbanken'=>['enabled'=>false,'primary'=>null,'alternatives'=>['natuursteen']]
]);
if($choices[0]['baseline']['code']!=='hout'||count($choices[0]['alternatives'])!==1
 ||$choices[1]['baseline']!==null||count($choices[1]['alternatives'])!==1)exit(1);
try {
 OfficeCalcRecipeChoices::resolve($sections,['stelkozijnen'=>['enabled'=>true,'primary'=>'hout','alternatives'=>['hout']]]);
 exit(1);
} catch (DomainException) {}
try {
 OfficeCalcRecipeChoices::resolve($sections,['onbekend'=>['enabled'=>false,'primary'=>null,'alternatives'=>[]]]);
 exit(1);
} catch (DomainException) {}
echo "RECIPE_CHOICES_CONTRACT=OK\n";
