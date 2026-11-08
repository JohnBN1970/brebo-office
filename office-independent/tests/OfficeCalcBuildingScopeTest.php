<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcBuildingScope.php';
use Brebo\Office\Calculation\OfficeCalcBuildingScope as S;
$input=['work_type'=>'renovation','occupancy'=>'occupied','floors'=>5,'access'=>'scaffold','building_dwelling_count'=>232,'dwelling_types'=>[['code'=>'A','count'=>120],['code'=>'B','count'=>112]]];
$result=S::normalize($input);
if ($result['dwelling_count']!==232 || count($result['dwelling_types'])!==2) throw new RuntimeException('Scope totals incorrect');
foreach ([
    ['building_dwelling_count'=>231],
    ['dwelling_types'=>[['code'=>'A','count'=>120],['code'=>'A','count'=>112]]],
    ['floors'=>0],
    ['occupancy'=>'unknown'],
    ['access'=>'unknown'],
] as $change) {
    try { S::normalize(array_replace($input,$change)); throw new RuntimeException('Invalid scope accepted'); }
    catch (InvalidArgumentException $expected) {}
}
echo "CALC_BUILDING_SCOPE=PASS\n";
