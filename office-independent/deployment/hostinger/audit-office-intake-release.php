<?php
declare(strict_types=1);

/** Read-only isolated Office-test audit. Never print config values or credentials. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = '/home/u213420663/domains/brebobv.nl/office_calc_test';
$configFile = $root . '/config/config.php';
$routerFile = '/home/u213420663/domains/brebobv.nl/public_html/office-test/index.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "ABORT: office_test_config_missing\n");
    exit(2);
}
$config = require $configFile;
if (!is_array($config) || ($config['OFFICE_BASE_PATH'] ?? null) !== '/office-test'
    || ($config['OFFICE_INTAKE_DB_USER'] ?? null) !== 'u213420663_office_test'
    || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', (string) ($config['OFFICE_INTAKE_PDO_DSN'] ?? ''))) {
    fwrite(STDERR, "ABORT: office_test_scope_invalid\n");
    exit(2);
}
echo "OFFICE_TEST_AUDIT_SCOPE_OK\n";
echo "OFFICE_TEST_ROUTER_PRESENT=" . (is_file($routerFile) ? 'yes' : 'no') . "\n";
if (!is_file($routerFile)) {
    exit(0);
}
$router = file_get_contents($routerFile);
if (!is_string($router)) {
    fwrite(STDERR, "ABORT: router_unreadable\n");
    exit(2);
}
echo "OFFICE_TEST_ROUTER_SYNTAX=";
$command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($routerFile) . ' 2>&1';
exec($command, $lines, $code);
echo $code === 0 ? "valid\n" : "invalid\n";
echo "OFFICE_TEST_INTAKE_ROUTE_PRESENT=" . (str_contains($router, '/office-test/api/intake/v1/receive') ? 'yes' : 'no') . "\n";
echo "OFFICE_TEST_INTAKE_ROUTE_FLAG_GUARD=" . (str_contains($router, "getenv('OFFICE_INTAKE_RECEIVER_ENABLED') !== 'enabled'") ? 'yes' : 'no') . "\n";
$matches = [];
if (preg_match('/\$root\s*=\s*[\x27\x22]([^\x27\x22]+)[\x27\x22]\s*;/', $router, $matches) !== 1) {
    echo "OFFICE_TEST_ACTIVE_RELEASE=unknown\\n";
    // Inspect only known isolated root; do not reveal arbitrary source or configuration.
    $possible = [];
    foreach (glob($root . '/release-*/private', GLOB_ONLYDIR) ?: [] as $candidate) {
        if (is_file($candidate . '/public/office-home.php')) {
            $possible[] = basename(dirname($candidate));
        }
    }
    sort($possible);
    echo "OFFICE_TEST_RELEASE_CANDIDATES=" . implode(',', $possible) . "\\n";
    echo "OFFICE_TEST_ROUTER_SHA256=" . hash_file('sha256', $routerFile) . "\\n";
    foreach ([
        'office-independent.php', 'router.php', 'bootstrap.php', 'office-test.php',
    ] as $safeMarker) {
        echo "OFFICE_TEST_ROUTER_HAS_" . strtoupper(str_replace(['.', '-'], '_', $safeMarker)) . "="
            . (str_contains($router, $safeMarker) ? 'yes' : 'no') . "\\n";
    }
    exit(0);
}
$release = $matches[1];
if (!str_starts_with($release, $root . '/release-')
    || !str_ends_with($release, '/private')
    || str_contains($release, '..')) {
    echo "OFFICE_TEST_ACTIVE_RELEASE=invalid_scope\n";
    exit(2);
}
echo "OFFICE_TEST_ACTIVE_RELEASE=" . basename(dirname($release)) . "\n";
echo "OFFICE_TEST_ACTIVE_PRIVATE_PRESENT=" . (is_dir($release) ? 'yes' : 'no') . "\n";
foreach ([
    'public/office-intake-receive.php',
    'src/Intake/OfficeIntakeSecureReceiver.php',
    'src/Intake/OfficeIntakeSourceAuthenticator.php',
    'src/Intake/OfficeIntakeReplayGuard.php',
    'src/Intake/OfficeCanonicalIntakeBuildingHandoff.php',
    'src/Buildings/OfficeIntakeBuildingResolver.php',
] as $file) {
    echo 'OFFICE_TEST_FILE_' . str_replace(['/', '.'], '_', $file) . '=' . (is_file($release . '/' . $file) ? 'yes' : 'no') . "\n";
}
echo "OFFICE_TEST_INTAKE_FLAG_ENABLED=" . (($config['OFFICE_INTAKE_RECEIVER_ENABLED'] ?? null) === 'enabled' ? 'yes' : 'no') . "\n";
echo "OFFICE_TEST_WEBSITE_SECRET_CONFIGURED=" . (isset($config['OFFICE_INTAKE_SECRET_WEBSITE']) && is_string($config['OFFICE_INTAKE_SECRET_WEBSITE']) && strlen($config['OFFICE_INTAKE_SECRET_WEBSITE']) >= 32 ? 'yes' : 'no') . "\n";
echo "OFFICE_TEST_EMAIL_SECRET_CONFIGURED=" . (isset($config['OFFICE_INTAKE_SECRET_EMAIL']) && is_string($config['OFFICE_INTAKE_SECRET_EMAIL']) && strlen($config['OFFICE_INTAKE_SECRET_EMAIL']) >= 32 ? 'yes' : 'no') . "\n";
echo "OFFICE_TEST_INTAKE_RELEASE_AUDIT_COMPLETE\n";
