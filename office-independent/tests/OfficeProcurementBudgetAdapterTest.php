<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/WorkingBudget/OfficeProcurementBudgetAdapter.php';
use Brebo\Office\WorkingBudget\OfficeProcurementBudgetAdapter as Adapter;

$order = ['source'=>'office_procurement_orders','order_id'=>7,'project_id'=>42,
    'building_id'=>5,'revision'=>1,'status'=>'ordered','amount_cents'=>250000,
    'currency'=>'EUR','vat_basis'=>'excluding'];
$result = Adapter::fromAwardedOrders(42, [$order]);
if ($result['awarded_cents'] !== 250000 || $result['actual_cents'] !== null
    || $result['open_commitment_cents'] !== null
    || $result['invoice_matching_state'] !== 'not_connected') {
    throw new RuntimeException('Unknown actuals must remain unknown.');
}
foreach ([[$order,$order],[array_replace($order,['project_id'=>43])],
    [array_replace($order,['status'=>'approved'])],
    [array_replace($order,['amount_cents'=>-1])]] as $invalid) {
    $rejected=false;
    try { Adapter::fromAwardedOrders(42,$invalid); }
    catch (InvalidArgumentException $e) { $rejected=true; }
    if (!$rejected) throw new RuntimeException('Invalid procurement input accepted.');
}
echo "OFFICE_PROCUREMENT_BUDGET_ADAPTER_TEST=PASS\n";
