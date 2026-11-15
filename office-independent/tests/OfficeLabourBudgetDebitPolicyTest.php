<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeLabourBudgetDebitPolicy.php';
use Brebo\Office\Inzet\OfficeLabourBudgetDebitPolicy as Policy;
$case = Policy::calculate(6*3600, 8*3600);
if ($case !== ['approved_seconds'=>21600,'budget_debit_seconds'=>28800,'variance_seconds'=>-7200]) {
    throw new RuntimeException('Six approved hours must debit eight planned hours');
}
$case = Policy::calculate(10*3600, 8*3600);
if ($case['approved_seconds'] !== 36000 || $case['budget_debit_seconds'] !== 28800 || $case['variance_seconds'] !== 7200) {
    throw new RuntimeException('Actual hours must remain distinct from planned budget debit');
}
foreach ([[0,28800],[-1,28800],[21600,0],[21600,-1],[86401,28800]] as [$approved,$planned]) {
    try { Policy::calculate($approved,$planned); throw new RuntimeException('Expected rejection'); }
    catch (InvalidArgumentException|DomainException $expected) {}
}
echo "LABOUR_BUDGET_DEBIT_POLICY_OK\n";
