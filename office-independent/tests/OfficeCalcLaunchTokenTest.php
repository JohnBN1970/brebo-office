<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcLaunchToken.php';

use Brebo\Office\Calculation\OfficeCalcLaunchToken;

$secret = str_repeat('k', 32);
$now = 1793960000;
$grant = str_repeat('a', 64);
$token = OfficeCalcLaunchToken::issue(12, 34, 56, $secret, $now, $grant);
$parts = explode('.', $token);
if (count($parts) !== 2 || !preg_match('/^[a-f0-9]{64}$/D', $parts[1])) {
    throw new RuntimeException('Malformed Calc token');
}
[$encoded, $signature] = $parts;
if (!hash_equals(hash_hmac('sha256', $encoded, $secret), $signature)) {
    throw new RuntimeException('Calc signature mismatch');
}
$payload = json_decode(base64_decode(strtr($encoded, '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
foreach (['v' => 1, 'calculation_id' => 12, 'project_id' => 34, 'actor_id' => 56, 'exp' => $now + 90, 'grant_ref' => $grant] as $key => $value) {
    if (($payload[$key] ?? null) !== $value) {
        throw new RuntimeException('Calc payload mismatch: ' . $key);
    }
}
if (!preg_match('/^[a-f0-9]{32}$/D', (string) ($payload['nonce'] ?? ''))) {
    throw new RuntimeException('Invalid Calc nonce');
}
if ($token === OfficeCalcLaunchToken::issue(12, 34, 56, $secret, $now, $grant)) {
    throw new RuntimeException('Calc nonce reused');
}
foreach ([
    static fn() => OfficeCalcLaunchToken::issue(0, 34, 56, $secret, $now),
    static fn() => OfficeCalcLaunchToken::issue(12, 0, 56, $secret, $now),
    static fn() => OfficeCalcLaunchToken::issue(12, 34, 0, $secret, $now),
    static fn() => OfficeCalcLaunchToken::issue(12, 34, 56, 'short', $now),
    static fn() => OfficeCalcLaunchToken::issue(12, 34, 56, $secret, $now, 'bad'),
] as $invalid) {
    try {
        $invalid();
        throw new RuntimeException('Invalid Calc input accepted');
    } catch (InvalidArgumentException $expected) {
    }
}
echo "CALC_LAUNCH_TOKEN_CONTRACT=PASS\n";
