<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Crm/OfficeCrmRelationPolicy.php';
use Brebo\Office\Crm\OfficeCrmRelationPolicy as Policy;
$complete=array_fill_keys(['all_orders_and_projects_completed','all_invoices_paid','no_open_leads_or_deals','no_open_actions_or_restpoints','no_open_complaints','no_active_maintenance'],true);
function expect(bool $actual,bool $expected,string $case): void {
    if ($actual!==$expected) throw new RuntimeException($case);
}
expect(Policy::canAutoArchive('person',['customer'],$complete),true,'Complete private customer');
foreach (['organization','company','unknown'] as $kind) expect(Policy::canAutoArchive($kind,['customer'],$complete),false,$kind);
expect(Policy::canAutoArchive('person',['prospect'],$complete),false,'Not a customer');
foreach ($complete as $condition=>$value) {
    $missing=$complete; unset($missing[$condition]);
    expect(Policy::canAutoArchive('person',['customer'],$missing),false,'Missing '.$condition);
    foreach ([false,null,0,1,'true','false',[]] as $invalid) {
        $evidence=$complete; $evidence[$condition]=$invalid;
        expect(Policy::canAutoArchive('person',['customer'],$evidence),false,'Unproven '.$condition);
    }
}
echo "CRM archival policy regression passed\n";
