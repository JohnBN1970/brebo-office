<?php
declare(strict_types=1);
// Verify routes exist but fail closed until explicit staging verification.
$root = dirname(__DIR__);
$router = file_get_contents($root . '/deployment/hostinger/office-test-index.php');
$home = file_get_contents($root . '/public/office-home.php');
if (!is_string($router) || !is_string($home)) {
    throw new RuntimeException('Missing Calc integration source');
}
foreach ([
    'OFFICE_CALC_LAUNCH_ENABLED',
    'OFFICE_CALC_ENABLED',
    'OFFICE_CALC_BROWSER_HANDOFF_ENABLED',
    'OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED',
    'OFFICE_CALC_SESSION_GRANTS_ENABLED',
    'OFFICE_CALC_GRANT_VALIDATION_ENABLED',
    'OFFICE_CALC_SHARED_SECRET',
    '/office-test/calc-werkbank',
    '/office-test/calc/start',
    '/office-test/api/workbench/v1/calculation-grants/validate',
] as $required) {
    if (!str_contains($router, $required)) {
        throw new RuntimeException('Calc router missing security gate or route: ' . $required);
    }
}
if (!str_contains($router, "getenv('OFFICE_CALC_LAUNCH_ENABLED') !== 'enabled'")
    || !str_contains($router, "getenv('OFFICE_CALC_GRANT_VALIDATION_ENABLED') !== 'enabled'")) {
    throw new RuntimeException('Calc routes do not fail closed');
}
if (!str_contains($router, "$root = getenv('OFFICE_PRIVATE_ROOT');")
    || !str_contains($router, 'preg_quote($testRoot')
    || str_contains($router, "$root = '/home/u213420663/domains/brebobv.nl/office_calc_test/release-next/private';")) {
    throw new RuntimeException('Calc router must resolve a validated active Office-test release');
}
if (!str_contains($home, 'OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED')) {
    throw new RuntimeException('Calc dashboard lost proxy verification gate');
}
echo "CALC_OFFICE_TEST_RECONNECT_GATE=ROUTES_PRESENT_DISABLED_BY_DEFAULT\n";
