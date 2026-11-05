<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficeBankSubmissionReadiness.php';

use Brebo\Office\Finance\OfficeBankSubmissionReadiness as Gate;

$checks = 0;
$assert = static function(bool $ok, string $reason) use (&$checks): void {
    if (!$ok) throw new RuntimeException($reason);
    ++$checks;
};
$empty = Gate::evaluate([]);
$assert($empty['ready'] === false, 'Missing readiness blocks');
$assert(count($empty['blockers']) === 3, 'All missing gates reported');
foreach ([
    Gate::OPERATOR_AUTHORIZED,
    Gate::BANK_PAYMENTS_ENABLED,
    Gate::BANK_PROVIDER_CONTRACT_VERIFIED,
] as $key) {
    $values = [
        Gate::OPERATOR_AUTHORIZED => true,
        Gate::BANK_PAYMENTS_ENABLED => true,
        Gate::BANK_PROVIDER_CONTRACT_VERIFIED => true,
    ];
    $values[$key] = false;
    $result = Gate::evaluate($values);
    $assert(!$result['ready'] && $result['blockers'] === [$key], 'Single blocker denies: '.$key);
    $values[$key] = 'true';
    $assert(!Gate::evaluate($values)['ready'], 'String truthy values deny: '.$key);
}
$pass = Gate::evaluate([
    Gate::OPERATOR_AUTHORIZED => true,
    Gate::BANK_PAYMENTS_ENABLED => true,
    Gate::BANK_PROVIDER_CONTRACT_VERIFIED => true,
]);
$assert($pass['ready'] && $pass['blockers'] === [], 'Only explicit all-true readiness passes');
$assert(Gate::evaluate(['operator_authorized' => true])['ready'] === false, 'Authorization alone never enables payments');
echo "OfficeBankSubmissionReadinessTest OK ($checks checks)\n";
