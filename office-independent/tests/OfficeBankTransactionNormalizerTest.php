<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Finance/OfficeBankTransactionNormalizer.php';

use Brebo\Office\Finance\OfficeBankTransactionNormalizer as Normalizer;

$checks = 0;
$assert = static function(bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    ++$checks;
};
$sample = [
    'provider' => 'test-provider',
    'account_id' => 'account-1',
    'transaction_id' => 'txn-1',
    'booking_date' => '2026-11-05',
    'currency' => 'EUR',
    'amount_minor' => -15250,
    'description' => 'Supplier invoice',
];
$result = Normalizer::normalize($sample);
$assert($result['amount_minor'] === -15250, 'Signed integer minor units preserved');
$assert($result['currency'] === 'EUR', 'Currency preserved');
$assert(strlen($result['deduplication_key']) === 64, 'Stable hashed transaction identity');
$assert(Normalizer::normalize($sample)['deduplication_key'] === $result['deduplication_key'], 'Idempotent key');
$changed = $sample;
$changed['account_id'] = 'account-2';
$assert(Normalizer::normalize($changed)['deduplication_key'] !== $result['deduplication_key'], 'Account-scoped identity');
$changed = $sample;
$changed['transaction_id'] = 'txn-2';
$assert(Normalizer::normalize($changed)['deduplication_key'] !== $result['deduplication_key'], 'Provider transaction identity');
$invalid = [
    ['amount_minor' => '15250'],
    ['booking_date' => '2026-02-30'],
    ['currency' => 'eur'],
    ['transaction_id' => ''],
    ['provider' => str_repeat('x', 129)],
    ['description' => str_repeat('x', 2049)],
];
foreach ($invalid as $mutation) {
    try {
        Normalizer::normalize(array_replace($sample, $mutation));
        throw new RuntimeException('Invalid transaction accepted: '.json_encode($mutation));
    } catch (DomainException $exception) {
        $assert(true, 'Malformed transaction denied');
    }
}
echo "OfficeBankTransactionNormalizerTest OK ($checks checks)\n";
