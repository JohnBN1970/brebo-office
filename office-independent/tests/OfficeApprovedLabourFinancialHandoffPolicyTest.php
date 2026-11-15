<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeApprovedLabourFinancialHandoffPolicy.php';
use Brebo\Office\Inzet\OfficeApprovedLabourFinancialHandoffPolicy as P;
$v=['approved_hours_id'=>7,'project_id'=>42,'person_id'=>5,
    'actual_cost_cents'=>25500,'cost_type'=>'labor','currency'=>'EUR',
    'source_sha256'=>str_repeat('a',64)];
$handoff=P::prepare($v,[]);
if($handoff['source_id']!==7 || $handoff['amount_cents']!==25500
    || $handoff['source_kind']!=='onsite_approved_hours') {
    throw new RuntimeException('Incorrect labour financial handoff');
}
foreach ([[$v,[7]],[array_replace($v,['actual_cost_cents'=>0]),[]],
    [array_replace($v,['cost_type'=>'material']),[]],
    [array_replace($v,['source_sha256'=>'invalid']),[]]] as [$entry,$existing]) {
    try {P::prepare($entry,$existing);throw new RuntimeException('Unsafe handoff accepted');}
    catch(DomainException $expected) {}
}
echo "APPROVED_LABOUR_FINANCIAL_HANDOFF_OK\n";
