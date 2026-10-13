<?php

declare(strict_types=1);

use Brebo\Office\Calculation\OfficeCalcLaunchToken;

require_once __DIR__ . '/../src/Calculation/OfficeCalcLaunchToken.php';

$secret = str_repeat('s', 32);
$token = OfficeCalcLaunchToken::issue(21, 42, 7, $secret, 1000);
[$encoded, $signature] = explode('.', $token, 2);
if (!hash_equals(hash_hmac('sha256', $encoded, $secret), $signature)) {
  throw new RuntimeException('Launch signature mismatch.');
}
$payload = json_decode(base64_decode(strtr($encoded, '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
if ($payload['v'] !== 1 || $payload['calculation_id'] !== 21 || $payload['project_id'] !== 42
  || $payload['actor_id'] !== 7 || $payload['exp'] !== 1120
  || !preg_match('/^[0-9a-f]{32}$/', $payload['nonce'])) {
  throw new RuntimeException('Launch payload mismatch.');
}
if (OfficeCalcLaunchToken::issue(21, 42, 7, $secret, 1000) === $token) {
  throw new RuntimeException('Launch nonce was reused.');
}
echo "Calc launch token contract passed.\n";
