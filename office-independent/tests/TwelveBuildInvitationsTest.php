<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Integrations/TwelveBuild/TwelveBuildClient.php';
require_once __DIR__ . '/../src/Integrations/TwelveBuild/TwelveBuildInvitations.php';

use Brebo\Office\Integrations\TwelveBuild\TwelveBuildClient;
use Brebo\Office\Integrations\TwelveBuild\TwelveBuildInvitations;

$calls = [];
$transport = static function (string $url, array $request, array $headers) use (&$calls): array {
    $calls[] = [$url, $request, $headers];
    return str_ends_with($url, '/tokens') ? ['token' => 'fixture-token'] : ['items' => []];
};
$client = new TwelveBuildClient('https://example.invalid/api', 'fixture-key', '/tokens', $transport);
$api = new TwelveBuildInvitations(
    $client,
    static fn (string $key): array => ['method' => 'POST', 'json' => ['login' => 'fixture', 'apiKey' => $key]],
    static fn (array $response): array => ['token' => $response['token'], 'expires_in' => 3600]
);
$api->list(['page' => 1]);
$api->communication('ABC_123');
$api->projectDocuments('ABC_123');
if (count($calls) !== 4) {
    throw new RuntimeException('Unexpected number of HTTP calls.');
}
if (!str_ends_with($calls[1][0], '/invitation/list') || $calls[1][1]['method'] !== 'POST') {
    throw new RuntimeException('Invitation list route mismatch.');
}
if (!str_ends_with($calls[2][0], '/invitation/ABC_123/communication')) {
    throw new RuntimeException('Communication route mismatch.');
}
if (!str_ends_with($calls[3][0], '/invitation/ABC_123/projectDocuments')) {
    throw new RuntimeException('Document route mismatch.');
}
foreach (['../evil', 'bad/id', ''] as $invalid) {
    try {
        $api->communication($invalid);
        throw new RuntimeException('Invalid invitation ID accepted.');
    } catch (InvalidArgumentException $expected) {
    }
}
echo "12Build invitation fixture tests passed\n";
