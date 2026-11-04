<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Crm/OfficeCrmManagerRevenue.php';
use Brebo\Office\Crm\OfficeCrmManagerRevenue as R;
$links = [
 ['principal_relation_id'=>1,'representative_relation_id'=>10,'representation_type'=>'property_management','valid_from'=>'2026-01-01'],
 ['principal_relation_id'=>2,'representative_relation_id'=>10,'representation_type'=>'property_management','valid_from'=>'2026-01-01'],
 ['principal_relation_id'=>3,'representative_relation_id'=>20,'representation_type'=>'property_management','valid_from'=>'2026-01-01'],
];
$invoices = [
 ['invoice_id'=>'a','customer_relation_id'=>1,'net_revenue_cents'=>12500000],
 ['invoice_id'=>'b','customer_relation_id'=>2,'net_revenue_cents'=>8500000],
 ['invoice_id'=>'c','customer_relation_id'=>3,'net_revenue_cents'=>19000000],
];
$totals = R::totals($invoices,$links,'2026-11-04');
if ($totals !== [10=>21000000,20=>19000000]) throw new RuntimeException('Revenue rollup mismatch');
$links[] = ['principal_relation_id'=>1,'representative_relation_id'=>20,'representation_type'=>'property_management'];
try { R::totals($invoices,$links,'2026-11-04'); throw new RuntimeException('Double attribution accepted'); }
catch (InvalidArgumentException $expected) {}
echo "Vastgoedbeheer revenue rollup OK\n";
