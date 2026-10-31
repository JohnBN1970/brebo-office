<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsEndpoint.php';

use Brebo\Office\Offer\OfficeRoleSettingsEndpoint;

$reflection = new ReflectionClass(OfficeRoleSettingsEndpoint::class);
if (!$reflection->hasMethod('handle')) {
    throw new RuntimeException('Office router adapter missing');
}
$source = file_get_contents(__DIR__ . '/../src/Offer/OfficeRoleSettingsEndpoint.php');
foreach (['$operatorId < 1', "$method === 'GET'", "$method === 'POST'", "status' => 405", "status' => 303", 'OfficeRoleSettingsController', 'OfficeRoleSettingsPage'] as $required) {
    if (!str_contains($source, $required)) {
        throw new RuntimeException('Missing endpoint safety check: ' . $required);
    }
}
echo "OfficeRoleSettingsEndpointTest: PASS\n";
