<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Calculation/OfficeCalcTypeQuantities.php';
use Brebo\Office\Calculation\OfficeCalcTypeQuantities;
$result=OfficeCalcTypeQuantities::calculate([
 'code'=>'K01','discipline'=>'kozijnen','width_m'=>'1.2000','height_m'=>'1.5000'
],[
 ['housing_type_id'=>1,'homes'=>12,'items_per_home'=>3],
 ['housing_type_id'=>2,'homes'=>6,'items_per_home'=>2]
]);
if($result['perimeter_per_item_m']!=='5.4000'
 ||$result['items_total']!=='48.0000'
 ||$result['perimeter_total_m']!=='259.2000'
 ||$result['area_total_m2']!=='86.4000'
 ||$result['housing_types'][0]['items_total']!=='36')exit(1);
$glass=OfficeCalcTypeQuantities::calculate([
 'code'=>'G01','discipline'=>'glas','width_m'=>'1.0000','height_m'=>'1.3000'
],[['housing_type_id'=>1,'homes'=>12,'items_per_home'=>2]]);
if($glass['area_total_m2']!=='31.2000')exit(1);
foreach([
 [['housing_type_id'=>1,'homes'=>12,'items_per_home'=>3],['housing_type_id'=>1,'homes'=>1,'items_per_home'=>1]],
 [['housing_type_id'=>1,'homes'=>12,'items_per_home'=>-1]]
] as $invalid){
 try{OfficeCalcTypeQuantities::calculate(['code'=>'K01','discipline'=>'kozijnen','width_m'=>'1.2','height_m'=>'1.5'],$invalid);exit(1);}
 catch(DomainException $e){}
}
echo "CALC_TYPE_QUANTITIES_CONTRACT=OK\n";
