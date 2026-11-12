<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmCanonicalDraftGate.php';
use Brebo\Office\Calculation\OfficeCalcCrmCanonicalDraftGate as Gate;

$crm = ['id'=>7,'opportunity_id'=>13,'crm_revision'=>3,'created_by'=>2,'project_id'=>null];
$link = ['crm_draft_id'=>7,'opportunity_id'=>13,'crm_revision'=>3,'calc_draft_id'=>98];
$calc = ['id'=>98,'operator_id'=>2,'opportunity_id'=>13,'crm_revision'=>3,'project_id'=>null];

$resolve = static fn(array $a,array $b,array $c,int $actor=2,int $opp=13,int $rev=3): int =>
    Gate::resolve($actor,$opp,$rev,
        static fn()=>$a, static fn()=>$c, static fn()=>$b);
if ($resolve($crm,$link,$calc) !== 98) throw new RuntimeException('Valid canonical ID not returned');

$bad = [
    [$crm, array_merge($link,['calc_draft_id'=>7]), $calc],
    [$crm, array_merge($link,['opportunity_id'=>14]), $calc],
    [$crm, array_merge($link,['crm_revision'=>4]), $calc],
    [$crm, array_merge($link,['calc_draft_id'=>0]), $calc],
    [$crm, $link, array_merge($calc,['id'=>7])],
    [$crm, $link, array_merge($calc,['opportunity_id'=>14])],
    [$crm, $link, array_merge($calc,['operator_id'=>9])],
    [$crm, $link, array_merge($calc,['project_id'=>1])],
    [array_merge($crm,['created_by'=>9]),$link,$calc],
    [array_merge($crm,['project_id'=>1]),$link,$calc],
    [[], $link, $calc],
    [$crm, [], $calc],
    [$crm, $link, []],
];
foreach ($bad as [$a,$b,$c]) {
    try { $resolve($a,$b,$c); }
    catch (DomainException) { continue; }
    throw new RuntimeException('Unsafe canonical mapping accepted');
}
try {
    Gate::resolve(2,13,3,static function(){ throw new RuntimeException('DB down'); },static fn()=>[],static fn()=>[]);
    throw new RuntimeException('DB failure incorrectly authorized');
} catch (DomainException $e) {
    if ($e->getMessage() !== 'CRM Calc canonical draft unavailable') throw $e;
}
echo "CRM_CALC_CANONICAL_DRAFT_GATE=PASS\n";
