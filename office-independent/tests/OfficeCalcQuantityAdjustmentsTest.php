<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcQuantityAdjustments.php';
use Brebo\Office\Calculation\OfficeCalcQuantityAdjustments as Adjustments;
$material=Adjustments::material('100','8','25');
if ($material['gross_quantity']!=='108.0000'||$material['extra_quantity']!=='8.0000'||$material['material_cost']!=='2700.0000') {
    throw new RuntimeException('Materiaalverlies berekening onjuist');
}
$labor=Adjustments::labor('40','12','45');
if ($labor['total_hours']!=='52.0000'||$labor['labor_cost']!=='2340.0000') {
    throw new RuntimeException('Extra arbeidsuren berekening onjuist');
}
foreach ([['100','101','25'],['-1','5','25'],['100','5','-1']] as $invalid) {
    try { Adjustments::material(...$invalid); }
    catch (DomainException) { continue; }
    throw new RuntimeException('Ongeldige invoer werd geaccepteerd');
}
$snapshot=Adjustments::snapshot('108.0000','100','8',null,'material');
if($snapshot['net_quantity']!=='100'||$snapshot['material_loss_percent']!=='8')throw new RuntimeException('Materiaalcorrectie snapshot onjuist');
$hours=Adjustments::snapshot('52.0000','40',null,'12','labor');
if($hours['extra_labor_hours']!=='12')throw new RuntimeException('Arbeidscorrectie snapshot onjuist');
$legacy=Adjustments::snapshot('15.0000',null,null,null,'material');
if($legacy['net_quantity']!=='15.0000')throw new RuntimeException('Legacy hoeveelheden onjuist');
$rejected=false;
try {Adjustments::snapshot('109.0000','100','8',null,'material');}
catch (DomainException) {$rejected=true;}
if(!$rejected)throw new RuntimeException('Mismatch accepted');
echo "CALC_QUANTITY_ADJUSTMENTS=PASS\n";
