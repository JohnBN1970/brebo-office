<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Project/OfficeProjectBudgetKpiView.php';
use Brebo\Office\Project\OfficeProjectBudgetKpiView as View;

$missing=View::render(['state'=>'baseline_missing','budget_cents'=>null,'commitment_cents'=>5000,'remaining_cents'=>null]);
if (!str_contains($missing,'Niet beschikbaar') || !str_contains($missing,'Onbekend') || str_contains($missing,'€ 0,00')) {
    throw new RuntimeException('Missing budget rendered incorrectly');
}
$normal=View::render(['state'=>'comparable','budget_cents'=>100000,'commitment_cents'=>65000,'remaining_cents'=>35000]);
if (!str_contains($normal,'€ 350,00') || !str_contains($normal,'Groen')) throw new RuntimeException('Wrong positive balance');
$over=View::render(['state'=>'comparable','budget_cents'=>100000,'commitment_cents'=>125000,'remaining_cents'=>-25000]);
if (!str_contains($over,'€ -250,00') || !str_contains($over,'Rood')) throw new RuntimeException('Wrong overrun');
$rejected=false;
try { View::render(['state'=>'baseline_missing','budget_cents'=>0,'commitment_cents'=>0,'remaining_cents'=>0]); }
catch(InvalidArgumentException) {$rejected=true;}
if (!$rejected) throw new RuntimeException('Missing baseline zeroed');
echo "PROJECT_BUDGET_KPI_VIEW_PASS\n";
