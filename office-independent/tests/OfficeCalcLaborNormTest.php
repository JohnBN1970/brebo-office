<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcLaborNorm.php';
use Brebo\Office\Calculation\OfficeCalcLaborNorm;
function expectLabor(bool $ok,string $reason):void{if(!$ok)throw new RuntimeException($reason);}
$a=OfficeCalcLaborNorm::calculate('100','norm','0.4','45');
expectLabor($a['hours']==='40.0000'&&$a['labor_total']==='1800.0000'&&$a['labor_unit_cost']==='18.0000','Norm -> uren/totaal');
$b=OfficeCalcLaborNorm::calculate('100','hours','50','45');
expectLabor($b['norm']==='0.5000'&&$b['labor_total']==='2250.0000','Uren -> norm');
$c=OfficeCalcLaborNorm::calculate('200','norm',$b['norm'],'45');
expectLabor($c['hours']==='100.0000','Hoeveelheid verandert, norm blijft leidend');
$d=OfficeCalcLaborNorm::calculate('100','hours','50','50');
expectLabor($d['labor_total']==='2500.0000','Tarief herberekent totaal');
foreach([['0','norm','1','45'],['100','wrong','1','45'],['100','norm','-1','45']]as$bad){
 try{OfficeCalcLaborNorm::calculate(...$bad);throw new RuntimeException('Ongeldige invoer geaccepteerd');}
 catch(DomainException){}
}
echo "CALC_LABOR_NORM=PASS\n";
