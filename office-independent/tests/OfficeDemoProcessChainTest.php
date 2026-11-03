<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Demo/OfficeDemoProcessChain.php';

use Brebo\Office\Demo\OfficeDemoProcessChain as Demo;

$scenario = Demo::scenario();
Demo::validate($scenario);
assert($scenario['project']['code'] === 'TEST-2026-001');
assert(count($scenario['stages']) === 14);
assert(in_array('payments', $scenario['stages']['finance'], true));
assert(in_array('hours', $scenario['stages']['inzet_onsite'], true));
Demo::assertTestEnvironment('/office-test', 'u213420663_office_test');
foreach ([['/office', 'u213420663_office_test'], ['/office-test', 'u213420663_office']] as [$path, $db]) {
    try {
        Demo::assertTestEnvironment($path, $db);
        throw new \LogicException('Expected test isolation rejection');
    } catch (\RuntimeException $e) {
        assert(str_contains($e->getMessage(), 'Refusing demo writes'));
    }
}
echo "OFFICE_DEMO_PROCESS_CHAIN_CONTRACT_OK\n";
