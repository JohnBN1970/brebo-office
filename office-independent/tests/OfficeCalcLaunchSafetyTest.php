<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Calculation/OfficeCalcLaunchToken.php';
require_once __DIR__ . '/../src/Calculation/OfficeCalcLaunchAuthorization.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmOperatorContext.php';

use Brebo\Office\Calculation\OfficeCalcLaunchToken;
use Brebo\Office\Calculation\OfficeCalcLaunchAuthorization;
use Brebo\Office\Intake\WebsiteCrmOperatorContext;

function check(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$secret = str_repeat('s', 32);
$token = OfficeCalcLaunchToken::issue(12, 34, 56, $secret, 1000);
[$encoded, $signature] = explode('.', $token, 2);
$payload = json_decode(base64_decode(strtr($encoded, '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
check($payload['v'] === 1, 'Invalid version');
check($payload['calculation_id'] === 12 && $payload['project_id'] === 34 && $payload['actor_id'] === 56, 'Incorrect IDs');
check($payload['exp'] === 1120, 'Unexpected expiry');
check((bool) preg_match('/^[a-f0-9]{32}$/', $payload['nonce']), 'Invalid nonce');
check(hash_equals(hash_hmac('sha256', $encoded, $secret), $signature), 'Invalid signature');
check($token !== OfficeCalcLaunchToken::issue(12, 34, 56, $secret, 1000), 'Nonce reused');

foreach ([[0,34,56], [12,0,56], [12,34,0]] as [$calc,$project,$actor]) {
  try {
    OfficeCalcLaunchToken::issue($calc, $project, $actor, $secret);
    throw new RuntimeException('Invalid identifiers accepted');
  } catch (InvalidArgumentException $expected) {}
}
try {
  OfficeCalcLaunchToken::issue(12, 34, 56, 'short');
  throw new RuntimeException('Weak secret accepted');
} catch (InvalidArgumentException $expected) {}

echo "Office Calc launch token safety checks passed.\n";
