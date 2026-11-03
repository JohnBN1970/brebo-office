<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Demo/OfficeDemoCalcHandoff.php';

function check(bool $condition,string $message):void {
    if(!$condition) throw new RuntimeException($message);
}
check(class_exists(\Brebo\Office\Demo\OfficeDemoCalcHandoff::class),'Missing handoff');
$reflection=new ReflectionClass(\Brebo\Office\Demo\OfficeDemoCalcHandoff::class);
check($reflection->hasMethod('forProject'),'Missing project contract');
$method=$reflection->getMethod('forProject');
check($method->isPublic(),'Handoff must be callable');
echo "DEMO_CALC_HANDOFF_CONTRACT_OK\n";
