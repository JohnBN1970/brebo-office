<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Calculation/OfficeCalcTransportVerifier.php';
require_once __DIR__ . '/../src/Calculation/OfficeCalcRequestReplayGuard.php';
require_once __DIR__ . '/../src/Calculation/OfficeCalcAuthenticatedContextGate.php';

use Brebo\Office\Calculation\OfficeCalcAuthenticatedContextGate;
use Brebo\Office\Calculation\OfficeCalcRequestReplayGuard;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "SKIP: PDO SQLite not installed.\n";
  exit(0);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_calc_request_nonces (request_id TEXT PRIMARY KEY, request_timestamp INTEGER NOT NULL)');
$secret = str_repeat('k', 32);
$gate = new OfficeCalcAuthenticatedContextGate(new OfficeCalcRequestReplayGuard($db), $secret);
$path = '/api/workbench/v1/projects/42/calculation-context';
$now = 1791890000;
$make = static function (string $method, string $url, string $requestId) use ($secret, $now): array {
  $canonical = implode("\n", [$method, $url, hash('sha256', ''), (string) $now, $requestId]);
  return [
    'X-BREBO-Timestamp' => (string) $now,
    'X-BREBO-Request-Id' => $requestId,
    'X-BREBO-Signature' => 'v1=' . hash_hmac('sha256', $canonical, $secret),
  ];
};
$id = '12345678-1234-4234-8234-123456789abc';
$headers = $make('GET', $path, $id);
if (!$gate->allow('GET', $path, '', $headers, $now)) {
  throw new RuntimeException('First valid signed request rejected.');
}
if ($gate->allow('GET', $path, '', $headers, $now)) {
  throw new RuntimeException('Signed request replay accepted.');
}
$bad = $make('GET', $path, '12345678-1234-4234-8234-123456789abd');
$bad['X-BREBO-Signature'] = 'v1=' . str_repeat('0', 64);
if ($gate->allow('GET', $path, '', $bad, $now)) {
  throw new RuntimeException('Bad signature accepted.');
}
$wrongPath = '/api/workbench/v1/projects/0/calculation-context';
if ($gate->allow('GET', $wrongPath, '', $make('GET', $wrongPath, '12345678-1234-4234-8234-123456789abe'), $now)) {
  throw new RuntimeException('Invalid project path accepted.');
}
if ($gate->allow('POST', $path, '', $make('POST', $path, '12345678-1234-4234-8234-123456789abf'), $now)) {
  throw new RuntimeException('Wrong method accepted.');
}
echo "Office Calc authenticated context gate checks passed.\n";
