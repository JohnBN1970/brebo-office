<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Demo/OfficeDemoProcessChain.php';

use Brebo\Office\Demo\OfficeDemoProcessChain as Demo;

$scenario = Demo::scenario();
Demo::validate($scenario);
assert($scenario['project']['code'] === 'TEST-2026-001');
assert(count($scenario['stages']) === 17);
assert($scenario['lifecycle']['origin'] === 'crm_intake');
assert($scenario['lifecycle']['project_creation_gate'] === 'accepted_execution_contract');
assert($scenario['lifecycle']['inspection'] === 'separate_commission_optional');
assert($scenario['lifecycle']['final_measurement'] === 'optional_after_execution_contract');
assert(array_search('contractacceptatie', array_keys($scenario['stages']), true) < array_search('projecten', array_keys($scenario['stages']), true));
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
$emptyMonitor = Demo::monitor();
assert($emptyMonitor['total_count'] === count($scenario['stages']));
assert($emptyMonitor['ready_count'] === 0);
assert($emptyMonitor['stages']['crm']['status'] === 'UNVERIFIED');
assert($emptyMonitor['stages']['finance']['status'] === 'UNVERIFIED');
$checkedMonitor = Demo::monitor([
    'crm' => ['schema' => true, 'records' => true, 'workflow' => true],
    'calculatie' => ['schema' => false, 'reason' => 'Test schema not installed'],
    'uitvoering' => ['schema' => true, 'records' => true],
    'finance' => ['schema' => true, 'records' => false, 'workflow' => false],
]);
assert($checkedMonitor['ready_count'] === 1);
assert($checkedMonitor['stages']['crm']['status'] === 'READY');
assert($checkedMonitor['stages']['calculatie']['status'] === 'BLOCKED_SCHEMA');
assert($checkedMonitor['stages']['calculatie']['reason'] === 'Test schema not installed');
assert($checkedMonitor['stages']['uitvoering']['status'] === 'PARTIAL');
assert($checkedMonitor['stages']['finance']['status'] === 'UNVERIFIED');
echo "OFFICE_DEMO_PROCESS_CHAIN_CONTRACT_OK\n";
