<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Buildings/OfficeDwellingTypeCalculationPlan.php';
use Brebo\Office\Buildings\OfficeDwellingTypeCalculationPlan;

$base=[['dwelling_type_id'=>1,'line_key'=>'kozijnen','component_code'=>'31','description'=>'Kozijnen','quantity'=>3,'unit'=>'st','recipe_id'=>5]];
$addresses=[['id'=>101,'dwelling_type_id'=>1],['id'=>102,'dwelling_type_id'=>1]];
$overrides=[
  ['address_id'=>102,'line_key'=>'kozijnen','action'=>'replace','quantity'=>4],
  ['address_id'=>102,'line_key'=>'glas','action'=>'add','description'=>'Extra glas','quantity'=>2,'unit'=>'m2'],
];
$rows=OfficeDwellingTypeCalculationPlan::expand($base,$addresses,$overrides);
if (count($rows)!==3 || $rows[0]['quantity']!==3.0 || $rows[1]['quantity']!==4.0
  || $rows[0]['source']!=='dwelling_type' || $rows[1]['source']!=='address_override'
  || $rows[2]['line_key']!=='glas') throw new RuntimeException('incorrect_type_expansion');
if ($base[0]['quantity']!==3) throw new RuntimeException('shared_template_mutated');
$excluded=OfficeDwellingTypeCalculationPlan::expand($base,$addresses,[['address_id'=>102,'line_key'=>'kozijnen','action'=>'exclude']]);
if (count($excluded)!==1 || $excluded[0]['address_id']!==101) throw new RuntimeException('exclude_failed');
try {
  OfficeDwellingTypeCalculationPlan::expand($base,$addresses,[['address_id'=>999,'line_key'=>'kozijnen','action'=>'exclude']]);
  throw new RuntimeException('unknown_address_accepted');
} catch (InvalidArgumentException $expected) {}
$groups=OfficeDwellingTypeCalculationPlan::aggregate($rows);
if (count($groups)!==3) throw new RuntimeException('unexpected_group_count');
$quantities=[];
foreach ($groups as $group) {
  $quantities[$group['line_key'].':'.$group['quantity']]=$group;
}
if (!isset($quantities['kozijnen:3'],$quantities['kozijnen:4'],$quantities['glas:2'])) {
  throw new RuntimeException('incorrect_aggregated_quantities');
}
if ($quantities['glas:2']['requires_recipe']!==true) {
  throw new RuntimeException('missing_recipe_not_flagged');
}
$twoSame=OfficeDwellingTypeCalculationPlan::expand($base,$addresses);
$merged=OfficeDwellingTypeCalculationPlan::aggregate($twoSame);
if (count($merged)!==1 || $merged[0]['quantity']!==6.0 || $merged[0]['address_count']!==2) {
  throw new RuntimeException('identical_dwelling_type_lines_not_merged');
}
echo "Dwelling-type calculation expansion and overrides OK\n";
