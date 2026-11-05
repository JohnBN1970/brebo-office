<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficeMoneybirdBankFeedAdapter.php';

use Brebo\Office\Finance\OfficeMoneybirdBankFeedAdapter;

function assertSameValue($expected, $actual): void {
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$entry = ['id' => 'mut-123', 'financial_account_id' => 'account-42', 'date' => '2026-11-05', 'amount' => '-35.5000', 'description' => 'Leverancier'];
$record = OfficeMoneybirdBankFeedAdapter::normalize($entry, 'EUR');
assertSameValue('moneybird', $record['provider']);
assertSameValue('account-42', $record['account_id']);
assertSameValue('mut-123', $record['transaction_id']);
assertSameValue(-3550, $record['amount_minor']);
assertSameValue('Leverancier', $record['description']);
assertSameValue($record['deduplication_key'], OfficeMoneybirdBankFeedAdapter::normalize($entry, 'EUR')['deduplication_key']);
assertSameValue(1, OfficeMoneybirdBankFeedAdapter::normalize([...$entry, 'amount' => '0.01'], 'EUR')['amount_minor']);

foreach (['1.0010', 'oops', '999999999999999.00'] as $badAmount) {
    try {
        OfficeMoneybirdBankFeedAdapter::normalize([...$entry, 'amount' => $badAmount], 'EUR');
        throw new RuntimeException('Invalid amount accepted: ' . $badAmount);
    } catch (DomainException $expected) {
        // Fail closed.
    }
}
echo "OfficeMoneybirdBankFeedAdapterTest OK\n";
