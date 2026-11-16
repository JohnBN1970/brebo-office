<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Integrations/TwelveBuild/TwelveBuildClient.php';

use Brebo\Office\Integrations\TwelveBuild\TwelveBuildClient;

$now = 1000;
$calls = [];
$transport = static function (string $url, array $request, array $headers) use (&$calls): array {
    $calls[] = [$url, $request, $headers];
    return str_ends_with($url, '/auth') ? ['access_token' => 'fixture-token'] : ['items' => []];
};
$client = new TwelveBuildClient('https://example.invalid', 'fixture-key', '/auth', $transport, static function () use (&$now): int { return $now; });
$build = static fn (string $key): array => ['method' => 'POST', 'body' => ['key' => $key]];
$parse = static fn (array $response): array => ['token' => $response['access_token'], 'expires_in' => 3600];
$client->request('/leads', ['method' => 'GET'], $build, $parse);
$client->request('/leads', ['method' => 'GET'], $build, $parse);
if (count($calls) !== 3 || $calls[1][2]['X-Auth-Token'] !== 'fixture-token') {
    throw new RuntimeException('Token caching/header test failed');
}
$now = 4571;
$client->request('/leads', ['method' => 'GET'], $build, $parse);
if (count($calls) !== 5) {
    throw new RuntimeException('Token refresh test failed');
}
try {
    $client->request('//evil.test', [], $build, $parse);
    throw new RuntimeException('Unsafe path accepted');
} catch (InvalidArgumentException $expected) {
}
echo "12Build connector fixture tests passed\n";
