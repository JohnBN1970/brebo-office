<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Workflow/OfficeCommercialWorkflow.php';
use Brebo\Office\Workflow\OfficeCommercialWorkflow as W;
assert_options(ASSERT_ACTIVE, 1);
$cases = [
 ['intake','qualify','qualified'],
 ['qualified','open_opportunity','opportunity'],
 ['opportunity','start_quotation','quotation_draft'],
 ['quotation_draft','submit_review','quotation_review'],
 ['quotation_review','approve','quotation_approved'],
 ['quotation_approved','send','quotation_sent'],
 ['quotation_sent','accept','accepted'],
 ['qualified','register_direct_order','accepted'],
 ['accepted','start_fulfilment','fulfilment'],
];
foreach ($cases as [$from,$event,$to]) {
    if (W::next($from,$event) !== $to) throw new RuntimeException('Unexpected transition');
}
foreach ([['intake','accept'],['opportunity','start_fulfilment'],['lost','approve']] as [$from,$event]) {
    try { W::next($from,$event); throw new RuntimeException('Illegal transition accepted'); }
    catch (DomainException $expected) {}
}
if (!W::requiresHumanApproval('accept') || W::allowsAutomaticExecution('register_direct_order')) {
    throw new RuntimeException('Human approval gate failed');
}
echo "Commercial workflow contract OK\n";
