<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/WorkingBudget/MaterialProcurementProjection.php';
use Brebo\Office\WorkingBudget\MaterialProcurementProjection as P;
$material = ['article_code'=>'KZ-01','specification'=>'100x120 HR++','unit'=>'st','quantity_milli'=>10000];
$order = ['article_code'=>'KZ-01','specification'=>'100x120 HR++','unit'=>'st','order_line_id'=>'PO-1','quantity_milli'=>6000];
$result = P::summarize([$material],[$order],[
    ['receipt_id'=>'R-1','order_line_id'=>'PO-1','quantity_milli'=>2000],
    ['receipt_id'=>'R-2','order_line_id'=>'PO-1','quantity_milli'=>1000],
]);
$row=$result[0];
if ($row['remaining_to_order_milli'] !== 4000 || $row['outstanding_delivery_milli'] !== 3000 || $row['received_quantity_milli'] !== 3000) throw new RuntimeException('Wrong partial delivery projection');
$failed=false;
try { P::summarize([$material],[$order],[['receipt_id'=>'R-3','order_line_id'=>'PO-1','quantity_milli'=>7000]]); }
catch (InvalidArgumentException $e) { $failed=true; }
if (!$failed) throw new RuntimeException('Overreceipt accepted');
$failed=false;
try { P::summarize([$material],[$order],[['receipt_id'=>'R-1','order_line_id'=>'PO-1','quantity_milli'=>1000],['receipt_id'=>'R-1','order_line_id'=>'PO-1','quantity_milli'=>1000]]); }
catch (InvalidArgumentException $e) { $failed=true; }
if (!$failed) throw new RuntimeException('Duplicate receipt accepted');
echo "MATERIAL_PROCUREMENT_PROJECTION_TEST=PASS\n";
