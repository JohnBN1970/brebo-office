<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCostBreakdown.php';
use Brebo\Office\Calculation\OfficeCalcCostBreakdown;

$r = OfficeCalcCostBreakdown::calculate('2.5', ['labor'=>'10','material'=>'20.25','equipment'=>'1.5','subcontract'=>'3','other'=>'0.25'], '9');
if ($r['unit_cost'] !== '35.0000' || $r['net_total'] !== '87.5000' || $r['vat_total'] !== '7.8750' || $r['gross_total'] !== '95.3750') {
    throw new RuntimeException('Kostenverdeling of btw onjuist.');
}
if ($r['columns']['labor']['total'] !== '25.0000' || $r['columns']['material']['total'] !== '50.6250') {
    throw new RuntimeException('Kostenkolommen onjuist.');
}
foreach ([['0', [], '21'], ['1', ['unknown'=>'1'], '21'], ['1', ['labor'=>'-1'], '21'], ['1', [], '101']] as $invalid) {
    try {
        OfficeCalcCostBreakdown::calculate(...$invalid);
    } catch (DomainException) {
        continue;
    }
    throw new RuntimeException('Ongeldige invoer niet geweigerd.');
}
echo "CALC_COST_BREAKDOWN=PASS\n";
