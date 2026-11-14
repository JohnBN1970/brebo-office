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
echo "CALC_QUANTITY_ADJUSTMENTS=PASS\n";
