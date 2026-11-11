<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/WorkingBudget/MaterialProcurementPanel.php';
use Brebo\Office\WorkingBudget\MaterialProcurementPanel;
$row = [
'article_code'=>'KZ-1<script>','specification'=>'100x120 & HR++','unit'=>'st',
'budget_quantity_milli'=>10000,'ordered_quantity_milli'=>6000,
'received_quantity_milli'=>2000,'remaining_to_order_milli'=>4000,
'outstanding_delivery_milli'=>4000,'overordered_milli'=>0,
];
$html=MaterialProcurementPanel::render([$row]);
if (str_contains($html,'<script>') || !str_contains($html,'&lt;script&gt;')
    || !str_contains($html,'100x120 &amp; HR++')
    || !str_contains($html,'10,000')) throw new RuntimeException('Material panel rendering failed');
$invalid=$row;
$invalid['received_quantity_milli']=-1;
try { MaterialProcurementPanel::render([$invalid]); throw new RuntimeException('Invalid quantity accepted'); }
catch (InvalidArgumentException $expected) {}
echo "MATERIAL_PROCUREMENT_PANEL_TEST=PASS\n";
