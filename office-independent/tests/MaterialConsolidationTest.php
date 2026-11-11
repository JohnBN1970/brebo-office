<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/WorkingBudget/MaterialConsolidation.php';
use Brebo\Office\WorkingBudget\MaterialConsolidation;
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$line = static fn(string $id, string $code, string $spec, int $qty, int $cost): array => [
    'source_line_id'=>$id, 'article_code'=>$code, 'specification'=>$spec,
    'unit'=>'st', 'quantity_milli'=>$qty, 'budget_cents'=>$cost,
    'activity_ref'=>'A1', 'building_id'=>1, 'zone_id'=>2,
];
$result = MaterialConsolidation::group([
    $line('1', ' KZ-1 ', '100x120 / HR++', 2000, 10000),
    $line('2', 'kz-1', '100x120 / HR++', 3000, 15000),
    $line('3', 'KZ-1', '100x140 / HR++', 1000, 7000),
    $line('4', '', '100x120 / HR++', 1000, 5000),
]);
check(count($result['groups']) === 2, 'different specifications must not merge');
check(count($result['unmatched']) === 1, 'unmatched must be visible');
$merged = array_values(array_filter($result['groups'], static fn(array $g): bool => $g['specification'] === '100x120 / HR++'))[0];
check($merged['quantity_milli'] === 5000 && $merged['budget_cents'] === 25000, 'totals');
check(count($merged['allocations']) === 2, 'source allocations retained');
try { MaterialConsolidation::group([$line('1','A','x',1,1), $line('1','A','x',1,1)]); throw new RuntimeException('duplicate accepted'); }
catch (InvalidArgumentException $expected) {}
echo "MATERIAL_CONSOLIDATION_TEST=PASS\n";
