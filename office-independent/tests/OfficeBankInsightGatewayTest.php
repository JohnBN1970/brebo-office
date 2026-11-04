<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Finance/OfficeBankInsightGateway.php';

use Brebo\Office\Finance\OfficeBankInsightGateway;

$requests = [];
$gateway = new OfficeBankInsightGateway(
  static function (string $kind, string $iban, array $params) use (&$requests): array {
    $requests[] = [$kind, $iban, $params];
    return $kind === 'balance' ? ['balance' => '100.00'] : ['activities' => [['transaction_id' => 'bank-1']], 'next_page_key' => 'page-2'];
  },
  static fn(string $operator, string $iban): bool => $operator === 'operator-1' && $iban === 'NL91ABNA0417164300'
);

assert($gateway->balance('operator-1', 'nl91 abna 0417 1643 00')['balance'] === '100.00');
assert($gateway->bookedActivities('operator-1', 'NL91ABNA0417164300', '2026-11-01', 'page-1')['next_page_key'] === 'page-2');
assert($requests[1] === ['activities', 'NL91ABNA0417164300', ['dateFrom' => '2026-11-01', 'nextPageKey' => 'page-1']]);

$denied = false;
try { $gateway->balance('operator-2', 'NL91ABNA0417164300'); } catch (DomainException) { $denied = true; }
if (!$denied || count($requests) !== 2) throw new RuntimeException('Unauthorized bank call');

$invalid = false;
try { $gateway->bookedActivities('operator-1', 'NL91ABNA0417164300', '2026-02-30'); } catch (InvalidArgumentException) { $invalid = true; }
if (!$invalid || count($requests) !== 2) throw new RuntimeException('Invalid date reached transport');

echo "OfficeBankInsightGatewayTest OK\n";
