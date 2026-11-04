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
echo "Finance CRM report contract OK\n";
