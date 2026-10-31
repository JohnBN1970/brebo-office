<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsEndpoint.php';

use Brebo\Office\Offer\OfficeRoleSettingsEndpoint;

$endpoint = new OfficeRoleSettingsEndpoint(new PDO('sqlite::memory:'));
foreach ([
    ['GET', 0, 'token', 403],
    ['POST', 0, 'token', 403],
    ['GET', 1, '', 403],
    ['DELETE', 1, 'token', 405],
] as [$method, $operatorId, $token, $expected]) {
    $result = $endpoint->handle($method, $operatorId, $token, '/office-test/instellingen/rollen');
    if ($result['status'] !== $expected) {
        throw new RuntimeException('Unexpected endpoint status for ' . $method);
    }
}
echo "OfficeRoleSettingsEndpointTest: PASS\n";
