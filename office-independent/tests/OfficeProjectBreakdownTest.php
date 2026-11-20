<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Project/OfficeProjectBreakdown.php';

use Brebo\Office\Project\OfficeProjectBreakdown;

$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};

$tree = OfficeProjectBreakdown::tree([
    ['id'=>1, 'parent_id'=>null, 'code'=>'1', 'title'=>'Bouw'],
    ['id'=>2, 'parent_id'=>1, 'code'=>'1.1', 'title'=>'Gevel'],
    ['id'=>3, 'parent_id'=>2, 'code'=>'1.1.1', 'title'=>'Kozijnen'],
]);
$assert(count($tree) === 1, 'One root expected');
$assert($tree[0]['children'][0]['children'][0]['title'] === 'Kozijnen', 'Nested child missing');

$reject = static function (array $items) use ($assert): void {
    try {
        OfficeProjectBreakdown::tree($items);
    } catch (InvalidArgumentException | DomainException $e) {
        return;
    }
    $assert(false, 'Invalid WBS hierarchy accepted');
};
$reject([['id'=>1,'title'=>'A'], ['id'=>1,'title'=>'B']]);
$reject([['id'=>1,'parent_id'=>2,'title'=>'A']]);
$reject([['id'=>1,'parent_id'=>2,'title'=>'A'], ['id'=>2,'parent_id'=>1,'title'=>'B']]);
$reject([['id'=>1,'title'=>'']]);

$matrix = OfficeProjectBreakdown::riskMatrix([
    ['status'=>'open','payload'=>['probability'=>5,'impact'=>5]],
    ['status'=>'mitigating','payload'=>['probability'=>2,'impact'=>3]],
    ['status'=>'closed','payload'=>['probability'=>5,'impact'=>5]],
]);
$assert($matrix['cells'][5][5] === 1, 'Closed risk counted');
$assert($matrix['cells'][2][3] === 1, 'Mitigating risk missing');
$assert($matrix['totals']['critical'] === 1, 'Critical band incorrect');
$assert($matrix['totals']['medium'] === 1, 'Medium band incorrect');

try {
    OfficeProjectBreakdown::riskMatrix([['status'=>'open','probability'=>6,'impact'=>1]]);
    $assert(false, 'Invalid risk score accepted');
} catch (InvalidArgumentException $e) {
    // Expected.
}
echo "OFFICE_PROJECT_BREAKDOWN_TEST_PASS\n";
