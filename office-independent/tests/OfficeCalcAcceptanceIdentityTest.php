<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcAcceptanceIdentity.php';
use Brebo\Office\Calculation\OfficeCalcAcceptanceIdentity as I;
$a=['status'=>'accepted','crm_opportunity_id'=>'crm-123','calculation_id'=>7,'accepted_revision'=>'v2'];
$k=I::key($a);
if (strlen($k)!==64 || I::key($a)!==$k) throw new RuntimeException('Unstable acceptance key');
I::assertNotTransferred($a,null);
I::assertNotTransferred($a,['acceptance_key'=>$k,'project_id'=>12,'work_budget_id'=>15]);
foreach ([
    [['status'=>'draft']+$a,null],
    [$a,['acceptance_key'=>'bad','project_id'=>12,'work_budget_id'=>15]],
    [$a,['acceptance_key'=>$k,'project_id'=>12]],
] as [$acceptance,$existing]) {
    try { I::assertNotTransferred($acceptance,$existing); throw new RuntimeException('Invalid transfer accepted'); }
    catch (InvalidArgumentException|DomainException $expected) {}
}
echo "CRM_CALC_ACCEPTANCE_IDENTITY=PASS\n";
