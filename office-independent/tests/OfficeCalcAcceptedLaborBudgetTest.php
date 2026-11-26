<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcAcceptedLaborBudget.php';
use Brebo\Office\Calculation\OfficeCalcAcceptedLaborBudget;
$a=['status'=>'accepted','crm_opportunity_id'=>'5','calculation_id'=>17,'accepted_revision'=>'rev-4'];
$lines=[
 ['line_id'=>'A','cost_type'=>'labor','labor_hours'=>'288.0000','quantity_decimal'=>'36.0000','unit_cost_decimal'=>'12.3456'],
 ['line_id'=>'B','cost_type'=>'labor','labor_hours'=>'103.6800'],
 ['line_id'=>'C','cost_type'=>'material','labor_hours'=>null],
];
$r=OfficeCalcAcceptedLaborBudget::prepare($a,$lines);
if($r['labor_hours']!=='391.6800'||$r['accepted_revision']!=='rev-4'||!$r['locked'])throw new RuntimeException('Labor snapshot mismatch');
foreach([
 [$a,[['line_id'=>'A','cost_type'=>'labor','labor_hours'=>null]]],
 [$a,[...$lines,$lines[0]]],
 [[...$a,'status'=>'draft'],$lines]
] as [$acceptance,$items]){
 try{OfficeCalcAcceptedLaborBudget::prepare($acceptance,$items);throw new RuntimeException('Expected rejection');}
 catch(DomainException $expected){}
}
echo "ACCEPTED_LABOR_BUDGET_OK\n";
