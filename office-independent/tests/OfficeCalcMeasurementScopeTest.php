<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Calculation/OfficeCalcMeasurementScope.php';
use Brebo\Office\Calculation\OfficeCalcMeasurementScope;
use DomainException as ErrorType;
$aggregate=OfficeCalcMeasurementScope::normalize(['mode'=>'aggregate','discipline'=>'kozijnen',
 'unit'=>'m2','source_ref'=>'building:1','quantity'=>'300.0000']);
$detailed=OfficeCalcMeasurementScope::normalize(['mode'=>'detailed','discipline'=>'voegwerk',
 'unit'=>'m2','source_ref'=>'building:1','positions'=>[
 ['id'=>'gevel-noord','quantity'=>'75.2500'],['id'=>'gevel-zuid','quantity'=>'80.0000']]]);
if($aggregate['quantity']!=='300.0000'||$aggregate['positions']!==[]
 ||$detailed['quantity']!=='155.2500'||count($detailed['positions'])!==2)exit(1);
$invalid=[
 ['mode'=>'aggregate','discipline'=>'glas','unit'=>'m2','source_ref'=>'building:1','quantity'=>'10.0000','positions'=>[['id'=>'x','quantity'=>'1.0000']]],
 ['mode'=>'detailed','discipline'=>'schilderwerk','unit'=>'m2','source_ref'=>'building:1','quantity'=>'100.0000','positions'=>[['id'=>'x','quantity'=>'1.0000']]],
 ['mode'=>'detailed','discipline'=>'metselwerk','unit'=>'m2','source_ref'=>'building:1','positions'=>[['id'=>'x','quantity'=>'1.0000'],['id'=>'x','quantity'=>'2.0000']]]
];
foreach($invalid as $input){try{OfficeCalcMeasurementScope::normalize($input);exit(1);}catch(ErrorType $e){}}
echo "CALC_MEASUREMENT_SCOPE_CONTRACT=OK\n";
