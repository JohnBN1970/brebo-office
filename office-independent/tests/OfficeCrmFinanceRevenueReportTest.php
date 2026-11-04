<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Crm/OfficeCrmManagerRevenue.php';
require_once __DIR__ . '/../src/Crm/OfficeCrmFinanceRevenueReport.php';
use Brebo\Office\Crm\OfficeCrmFinanceRevenueReport as R;
$links = [['principal_relation_id'=>1,'representative_relation_id'=>10,'representation_type'=>'property_management']];
$invoices = [
 ['invoice_id'=>'A','customer_relation_id'=>1,'net_revenue_cents'=>12500000,'invoice_date'=>'2026-02-01','status'=>'paid'],
 ['invoice_id'=>'B','customer_relation_id'=>1,'net_revenue_cents'=>900000,'invoice_date'=>'2026-02-02','status'=>'draft'],
 ['invoice_id'=>'C','customer_relation_id'=>1,'net_revenue_cents'=>500000,'invoice_date'=>'2025-02-01','status'=>'paid'],
 ['invoice_id'=>'D','customer_relation_id'=>1,'net_revenue_cents'=>100000,'invoice_date'=>'2026-03-01','status'=>'credited'],
];
if (R::build($invoices,$links,2026) !== [10=>12500000]) throw new RuntimeException('Finance year/status rollup mismatch');
$changedManager = [
 ['principal_relation_id'=>1,'representative_relation_id'=>10,'representation_type'=>'property_management','valid_until'=>'2026-06-30'],
 ['principal_relation_id'=>1,'representative_relation_id'=>20,'representation_type'=>'property_management','valid_from'=>'2026-07-01'],
];
$datedInvoices = [
 ['invoice_id'=>'E','customer_relation_id'=>1,'net_revenue_cents'=>10000,'invoice_date'=>'2026-03-01','status'=>'paid'],
 ['invoice_id'=>'F','customer_relation_id'=>1,'net_revenue_cents'=>20000,'invoice_date'=>'2026-09-01','status'=>'paid'],
];
if (R::build($datedInvoices,$changedManager,2026) !== [10=>10000,20=>20000]) throw new RuntimeException('Historical manager attribution failed');
echo "Finance CRM report contract OK\n";
