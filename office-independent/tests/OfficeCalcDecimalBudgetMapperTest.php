<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcDecimalBudgetMapper.php';
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcWorkBudgetHandoff.php';
use Brebo\Office\Calculation\OfficeCalcDecimalBudgetMapper;
use Brebo\Office\Calculation\OfficeCalcWorkBudgetHandoff;
if (!function_exists('bcmul')) { echo "BCMATH_REQUIRED\n"; exit(1); }
$costs=['labor'=>'12.3456','material'=>'0','equipment'=>'0','subcontract'=>'0','other'=>'0'];
$mapped=OfficeCalcDecimalBudgetMapper::map('row-1','1.2500',$costs);
if (count($mapped)!==1 || $mapped[0]['quantity_decimal']!=='1.2500' || $mapped[0]['unit_cost_decimal']!=='12.3456' || $mapped[0]['unit_cost_cents']!==1543) throw new RuntimeException('Decimal quantity or rounding mismatch.');
$accepted=['crm_opportunity_id'=>'opp-1','calculation_id'=>1,'accepted_revision'=>'rev-1','status'=>'accepted'];
$budget=OfficeCalcWorkBudgetHandoff::prepare($accepted,$mapped);
if ($budget['items'][0]['budget_cents']!==1543 || $budget['items'][0]['quantity']!==1) throw new RuntimeException('Legacy budget compatibility failed.');
foreach (['1.12345','-1','0','1e3'] as $bad) {
    try { OfficeCalcDecimalBudgetMapper::map('row-1',$bad,$costs); throw new RuntimeException('Accepted invalid quantity '.$bad); }
    catch (DomainException $expected) {}
}
echo "CALC_DECIMAL_BUDGET_MAPPER=PASS\n";
