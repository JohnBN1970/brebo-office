<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/WorkingBudget/MaterialRequirementConsolidator.php';
use Brebo\Office\WorkingBudget\MaterialRequirementConsolidator as C;
$base = ['cost_type'=>'material','source_line_id'=>1,'quantity'=>200,'unit_cost_cents'=>125,
    'activity_ref'=>'A','article_identity'=>'supplier:SKU-1','specification'=>'white',
    'unit'=>'m2','quantity_scale'=>100];
$a = C::consolidate([$base, array_replace($base,['source_line_id'=>2,'quantity'=>300,'activity_ref'=>'B']),
    array_replace($base,['source_line_id'=>3,'specification'=>'black']),
    array_replace($base,['source_line_id'=>4,'article_identity'=>null])]);
if (count($a['groups'])!==2 || $a['groups'][0]['budget_quantity']!==500
    || count($a['groups'][0]['allocations'])!==2 || count($a['unmatched'])!==1
    || $a['groups'][0]['remaining_to_order']!==null) {
    throw new RuntimeException('Material grouping or provenance incorrect.');
}
foreach ([
    [$base,$base],
    [array_replace($base,['quantity'=>-1])],
    [array_replace($base,['quantity'=>PHP_INT_MAX]),array_replace($base,['source_line_id'=>2])],
] as $invalid) {
    $rejected=false;
    try { C::consolidate($invalid); }
    catch (InvalidArgumentException|OverflowException $e) { $rejected=true; }
    if (!$rejected) throw new RuntimeException('Invalid material requirement accepted.');
}
$b=C::consolidate([array_replace($base,['source_line_id'=>5,'unit'=>'pcs']),
    array_replace($base,['source_line_id'=>6,'quantity_scale'=>1000])]);
if (count($b['groups'])!==2) throw new RuntimeException('Incompatible units merged.');
echo "MATERIAL_REQUIREMENT_CONSOLIDATOR_TEST=PASS\n";
