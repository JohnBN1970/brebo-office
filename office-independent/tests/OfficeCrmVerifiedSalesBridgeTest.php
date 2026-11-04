<?php
declare(strict_types=1);
foreach (['OfficeCrmManagerRevenue','OfficeCrmFinanceRevenueReport','OfficeCrmVerifiedSalesBridge'] as $class) {
    require_once __DIR__ . '/../src/Crm/' . $class . '.php';
}
use Brebo\Office\Crm\OfficeCrmVerifiedSalesBridge as B;
$links = [['principal_relation_id'=>1,'representative_relation_id'=>10,'representation_type'=>'property_management']];
$invoice = ['source'=>'office_finance_sales_invoice','mapping_verified'=>true,'authorized'=>true,'currency'=>'EUR',
    'invoice_id'=>'INV-1','customer_relation_id'=>1,'net_revenue_cents'=>250000,'invoice_date'=>'2026-02-04','status'=>'paid'];
if (B::report([$invoice],$links,2026) !== [10=>250000]) throw new RuntimeException('Verified Finance report mismatch');
foreach (['mapping_verified'=>false,'authorized'=>false,'source'=>'office_finance_invoices','currency'=>'USD','customer_relation_id'=>0] as $field=>$bad) {
    try { B::report([array_replace($invoice,[$field=>$bad])],$links,2026); throw new RuntimeException('Unsafe Finance row accepted: '.$field); }
    catch (InvalidArgumentException $expected) {}
}
try { B::report([$invoice,$invoice],$links,2026); throw new RuntimeException('Duplicate accepted'); }
catch (InvalidArgumentException $expected) {}
echo "Verified Finance CRM bridge OK\n";
