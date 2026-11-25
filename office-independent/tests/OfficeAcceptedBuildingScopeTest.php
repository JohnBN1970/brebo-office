<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Calculation/OfficeAcceptedBuildingScope.php';
use Brebo\Office\Calculation\OfficeAcceptedBuildingScope as Scope;
$accepted=[['line_id'=>'A:labor','building_id'=>10],['line_id'=>'A:material','building_id'=>10],['line_id'=>'B:labor','building_id'=>20],['line_id'=>'C:labor','building_id'=>30],['line_id'=>'G:other','building_id'=>null]];
Scope::verify($accepted,array_reverse($accepted));
$changed=$accepted;$changed[0]['building_id']=20;
$missing=$accepted;unset($missing[4]['building_id']);
$duplicate=$accepted;$duplicate[]=$accepted[0];
$general=$accepted;$general[0]['building_id']=null;
$invalid=$accepted;$invalid[0]['building_id']='10';
foreach ([$changed,$missing,$duplicate,$general,$invalid,array_slice($accepted,1)] as $bad) {
    try {Scope::verify($accepted,$bad);throw new RuntimeException('Invalid scope accepted');}
    catch (DomainException $expected) {}
}
echo "OfficeAcceptedBuildingScopeTest OK\n";
