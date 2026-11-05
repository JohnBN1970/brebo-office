<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$router = file_get_contents($root . '/deployment/hostinger/office-test-index.php');
$handler = file_get_contents($root . '/public/office-intake-receive.php');
if (!is_string($router) || !is_string($handler)) {
    throw new RuntimeException('missing_route_files');
}
foreach ([
    "'/office-test/api/intake/v1/receive'",
    "getenv('OFFICE_INTAKE_RECEIVER_ENABLED') !== 'enabled'",
    "require \$private . '/public/office-intake-receive.php'",
] as $expected) {
    if (!str_contains($router, $expected)) {
        throw new RuntimeException('router_guard_missing');
    }
}
foreach ([
    "getenv('OFFICE_BASE_PATH') !== '/office-test'",
    "getenv('OFFICE_INTAKE_RECEIVER_ENABLED') !== 'enabled'",
    "'POST'",
    "'HTTPS'",
    "strlen(\$raw) > 8192",
    "OFFICE_INTAKE_SECRET_WEBSITE",
    "OFFICE_INTAKE_SECRET_EMAIL",
    "u213420663_office_test",
    "OfficeIntakeSecureReceiver",
] as $expected) {
    if (!str_contains($handler, $expected)) {
        throw new RuntimeException('handler_guard_missing');
    }
}
echo "OfficeIntakeHttpRouteContractTest OK\n";
