<?php
declare(strict_types=1);

/**
 * Fail-closed contract: Calc must not be advertised as connected until
 * the independent Office-test router has a real, authorized handoff.
 * This check intentionally does not enable any production feature flags.
 */
$root = dirname(__DIR__);
$router = file_get_contents($root . '/deployment/hostinger/office-test-index.php');
$home = file_get_contents($root . '/public/office-home.php');
$mount = file_get_contents($root . '/src/Intake/OfficeMount.php');
foreach ([$router, $home, $mount] as $source) {
    if (!is_string($source) || $source === '') {
        throw new RuntimeException('Calc integration source missing');
    }
}
if (!str_contains($mount, "'calc-werkbank' => '/calc-werkbank'")) {
    throw new RuntimeException('Calc mount contract changed');
}
if (!str_contains($home, "OFFICE_CALC_BROWSER_HANDOFF_ENABLED")
    || !str_contains($home, "OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED")) {
    throw new RuntimeException('Calc dashboard lost its handoff gate');
}
// This guard must remain until the route is replaced by a tested,
// session- and project-authorized implementation.
if (!str_contains($router, "getenv('OFFICE_CALC_LAUNCH_ENABLED') === 'enabled'")) {
    throw new RuntimeException('Office-test Calc launch fail-closed guard removed without verified replacement');
}
if (str_contains($router, "'/office-test/calc-werkbank' =>")) {
    throw new RuntimeException('Calc route added while launch remains blocked');
}
echo "CALC_OFFICE_TEST_RECONNECT_GATE=BLOCKED_SAFE\n";
