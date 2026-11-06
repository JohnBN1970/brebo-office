<?php
declare(strict_types=1);

// Static regression assertions: these must pass before staging deployment.
$root = dirname(__DIR__);
$router = file_get_contents($root . '/deployment/hostinger/office-test-index.php');
$handoff = file_get_contents($root . '/public/office-calc-browser-handoff.php');
$launch = file_get_contents($root . '/public/office-calc-launch.php');
$grant = file_get_contents($root . '/public/office-calc-grant-validate.php');
foreach ([$router, $handoff, $launch, $grant] as $file) {
    if (!is_string($file) || $file === '') throw new RuntimeException('Missing Calc adapter source');
}
foreach ([
    '/office-test/calc-werkbank',
    '/office-test/calc/start',
    '/api/workbench/v1/calculation-grants/validate',
] as $route) {
    if (!str_contains($router, $route)) throw new RuntimeException('Missing Calc route: ' . $route);
}
foreach ([
    "fetch('/office-test/calc/start'",
    "fetch('/calc-app/api/launch/consume'",
    "window.location.replace('/calc-app/')",
    "credentials: 'same-origin'",
    "redirect: 'error'",
] as $required) {
    if (!str_contains($handoff, $required)) throw new RuntimeException('Browser contract drift: ' . $required);
}
if (str_contains($handoff, 'location.search') || str_contains($handoff, 'localStorage.setItem')
    || str_contains($handoff, 'sessionStorage.setItem')
    || preg_match('/location\.(?:href|assign|replace)\s*\([^)]*token/', $handoff)) {
    throw new RuntimeException('Unsafe token handoff');
}
foreach ([
    'OFFICE_CALC_ENABLED', 'OFFICE_CALC_LAUNCH_ENABLED',
    'OFFICE_CALC_BROWSER_HANDOFF_ENABLED', 'OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED',
] as $flag) {
    if (!str_contains($handoff, $flag) || !str_contains($launch, $flag)) {
        throw new RuntimeException('Missing common launch gate: ' . $flag);
    }
}
if (!str_contains($launch, 'CONTENT_TYPE') || !str_contains($launch, 'CONTENT_LENGTH')
    || !str_contains($launch, '(int)$contentLength > 2048')) {
    throw new RuntimeException('Launch request size/type gate missing');
}
if (!str_contains($launch, 'OfficeCalcLaunchCsrf::verify')
    || !str_contains($launch, 'OfficeCalcLaunchCsrf::validOrigin')
    || !str_contains($grant, 'OFFICE_CALC_GRANT_VALIDATION_ENABLED')) {
    throw new RuntimeException('Missing authentication or grant gate');
}
echo "CALC_BROWSER_HANDOFF_CONTRACT=PASS\n";
