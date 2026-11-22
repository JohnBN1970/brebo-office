<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormAuthorization.php';
use Brebo\Office\Process\OfficeProcessFormAuthorization as A;
$allow=static fn(string $actor,string $action,string $type,int $id,string $code): bool => $actor==='real-operator' && $action==='view' && $type==='project' && $id===42 && $code==='FR-300';
if (!A::permitted($allow,'real-operator','view','project',42,'FR-300')) throw new RuntimeException('Expected real grant');
foreach ([
 ['preview-user','view','project',42,'FR-300'],
 ['real-operator','approve','project',42,'FR-300'],
 ['real-operator','view','project',43,'FR-300'],
 ['real-operator','view','project',42,'FR-999'],
 ['real-operator','delete','project',42,'FR-300'],
] as $args) {
    if (A::permitted($allow,...$args)) throw new RuntimeException('Unauthorized grant');
}
if (A::permitted(static fn()=>1,'real-operator','view','project',42,'FR-300')) throw new RuntimeException('Nonboolean grant accepted');
if (A::permitted(static function(){throw new RuntimeException('resolver unavailable');},'real-operator','view','project',42,'FR-300')) throw new RuntimeException('Resolver failure accepted');
try {
    A::assertPermitted($allow,'preview-user','approve','project',42,'FR-300');
    throw new RuntimeException('Expected denial');
} catch (InvalidArgumentException $expected) {}
echo "Process form authorization fail-closed contracts OK\n";
