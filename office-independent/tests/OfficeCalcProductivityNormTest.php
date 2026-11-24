<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Calculation/OfficeCalcProductivityNorm.php';
use Brebo\Office\Calculation\OfficeCalcProductivityNorm;
$measures=['items_total'=>'90.0000','perimeter_total_m'=>'540.0000','area_total_m2'=>'315.0000'];
$norm=['unit'=>'st','quantity_per_day'=>'2.0000','hours_per_day'=>'8.0000','crew_size'=>2];
$st=OfficeCalcProductivityNorm::convert($measures,$norm,'st');
$m=OfficeCalcProductivityNorm::convert($measures,$norm,'m');
$m2=OfficeCalcProductivityNorm::convert($measures,$norm,'m2');
foreach([$st,$m,$m2] as $result){
 if($result['planned_crew_days']!=='45.00000000'||$result['planned_labor_hours']!=='720.00000000')exit(1);
}
if($m2['display_quantity']!=='315.0000'
 ||$m2['quantity_per_day']!=='7.00000000'
 ||$m['quantity_per_day']!=='12.00000000'
 ||$m2['source_norm']['unit']!=='st')exit(1);
try{OfficeCalcProductivityNorm::convert($measures,$norm,'kg');exit(1);}catch(DomainException $e){}
echo "CALC_PRODUCTIVITY_NORM=OK\n";
