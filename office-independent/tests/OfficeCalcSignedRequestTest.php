<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcSignedRequest.php';
use Brebo\Office\Calculation\OfficeCalcSignedRequest;

$secret = str_repeat('s', 32);
$body = '{"reference":"' . str_repeat('a', 64) . '"}';
$time = 1793960000;
$requestId = '123e4567-e89b-42d3-a456-426614174000';
$path = '/api/workbench/v1/calculation-grants/validate';
$canonical = implode("\n", ['POST', $path, hash('sha256', $body), (string)$time, $requestId]);
$signature = 'v1=' . hash_hmac('sha256', $canonical, $secret);
$verify = static fn(string $method, string $pathValue, string $raw, int $now, string $sig): bool =>
    OfficeCalcSignedRequest::verify($method, $pathValue, $raw, (string)$time, $requestId, $sig, $secret, $now);
if (!$verify('POST', $path, $body, $time, $signature)) {
    throw new RuntimeException('Valid Calc signature rejected');
}
foreach ([
    ['GET', $path, $body, $time, $signature],
    ['POST', '/other', $body, $time, $signature],
    ['POST', $path, '{}', $time, $signature],
    ['POST', $path, $body, $time + 91, $signature],
    ['POST', $path, $body, $time, 'v1=' . str_repeat('0', 64)],
] as [$method, $pathValue, $raw, $now, $sig]) {
    if ($verify($method, $pathValue, $raw, $now, $sig)) {
        throw new RuntimeException('Invalid Calc signed request accepted');
    }
}
// The Calc project context GET must use the exact canonical path and empty body.
$contextPath = '/api/workbench/v1/projects/123/calculation-context';
$getCanonical = implode("\n", ['GET', $contextPath, hash('sha256', ''), (string)$time, $requestId]);
$getSignature = 'v1=' . hash_hmac('sha256', $getCanonical, $secret);
if (!$verify('GET', $contextPath, '', $time, $getSignature)) {
    throw new RuntimeException('Valid signed Calc project context GET rejected');
}
foreach ([
    ['GET', '/api/workbench/v1/projects/0123/calculation-context', '', $time, $getSignature],
    ['GET', '/api/workbench/v1/projects/123/calculation-context/extra', '', $time, $getSignature],
    ['GET', $contextPath, '{}', $time, $getSignature],
    ['POST', $contextPath, '', $time, $getSignature],
    ['GET', $contextPath, '', $time + 91, $getSignature],
] as [$method, $pathValue, $raw, $now, $sig]) {
    if ($verify($method, $pathValue, $raw, $now, $sig)) {
        throw new RuntimeException('Invalid Calc project context request accepted');
    }
}
echo "CALC_SIGNED_GRANT_TRANSPORT=PASS\n";
