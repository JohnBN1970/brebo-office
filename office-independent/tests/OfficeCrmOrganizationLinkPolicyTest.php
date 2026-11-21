<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Crm/OfficeCrmOrganizationLinkPolicy.php';
use Brebo\Office\Crm\OfficeCrmOrganizationLinkPolicy as P;
$edges = [
    ['parent_relation_id' => 1, 'child_relation_id' => 2, 'link_type' => 'subsidiary'],
    ['parent_relation_id' => 2, 'child_relation_id' => 3, 'link_type' => 'branch'],
];
P::validate(1, 4, 'subsidiary', $edges);
P::validate(4, 5, 'group_member', $edges);
foreach ([
    [3, 1, 'subsidiary', $edges],
    [4, 3, 'branch', $edges],
    [1, 1, 'subsidiary', []],
    [1, 2, 'property_management', []],
    [0, 2, 'branch', []],
] as $case) {
    $rejected = false;
    try { P::validate(...$case); }
    catch (InvalidArgumentException $e) { $rejected = true; }
    if (!$rejected) { throw new RuntimeException('Invalid hierarchy accepted'); }
}
echo "CRM organization hierarchy policy OK\n";
