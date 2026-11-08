<?php
declare(strict_types=1);
/**
 * Safe, code-only Office-test governance deployment.
 * Does not modify routing, configuration, database or existing differing files.
 */
if (PHP_SAPI !== 'cli' || getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_GOVERNANCE_CODE_DEPLOY') !== 'explicit') {
    fwrite(STDERR, "GOVERNANCE_DEPLOY_SCOPE_DENIED\n");
    exit(2);
}
$base = '/home/u213420663/domains/brebobv.nl/office_calc_test';
$cfgFile = $base . '/config/config.php';
if (!is_file($cfgFile)) {
    fwrite(STDERR, "GOVERNANCE_DEPLOY_CONFIG_MISSING\n");
    exit(2);
}
$config = require $cfgFile;
$root = is_array($config) && ($config['OFFICE_BASE_PATH'] ?? null) === '/office-test'
    && is_string($config['OFFICE_PRIVATE_ROOT'] ?? null)
    ? realpath($config['OFFICE_PRIVATE_ROOT']) : false;
$baseReal = realpath($base);
if (!is_string($root) || $baseReal === false
    || !str_starts_with($root, $baseReal . DIRECTORY_SEPARATOR)
    || preg_match('~/release-[^/]+/private$~D', $root) !== 1
    || !is_file($root . '/public/office-project.php')
    || !is_file('/home/u213420663/domains/brebobv.nl/public_html/office-test/index.php')) {
    fwrite(STDERR, "GOVERNANCE_DEPLOY_ACTIVE_RELEASE_UNVERIFIED\n");
    exit(2);
}
$stage = $base . '/.governance-runtime-stage/office-independent';
$files = [
    'public/office-governance.php',
    'src/Governance/bootstrap.php',
    'src/Governance/OfficeGovernanceService.php',
    'src/Governance/OfficeGovernanceCsrf.php',
    'src/Governance/OfficeGovernanceSchema.php',
    'src/Governance/OfficeProcurementOrderSource.php',
    'src/Governance/Contract/OfficeGovernanceStore.php',
    'src/Governance/Infrastructure/PdoOfficeGovernanceStore.php',
];
foreach ($files as $relative) {
    $from = $stage . '/' . $relative;
    $to = $root . '/' . $relative;
    if (!is_file($from) || is_link($from) || !is_readable($from)) {
        fwrite(STDERR, "GOVERNANCE_DEPLOY_STAGED_SOURCE_MISSING: " . $relative . "\n");
        exit(2);
    }
    if (is_link($to) || (file_exists($to) && !is_file($to))) {
        fwrite(STDERR, "GOVERNANCE_DEPLOY_TARGET_UNSAFE: " . $relative . "\n");
        exit(2);
    }
    if (is_file($to) && !hash_equals(hash_file('sha256', $from), hash_file('sha256', $to))) {
        fwrite(STDERR, "GOVERNANCE_DEPLOY_TARGET_DIFFERS: " . $relative . "\n");
        exit(2);
    }
}
foreach ($files as $relative) {
    $from = $stage . '/' . $relative;
    $to = $root . '/' . $relative;
    if (is_file($to)) {
        echo "GOVERNANCE_CODE_ALREADY_PRESENT: " . $relative . "\n";
        continue;
    }
    $dir = dirname($to);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        fwrite(STDERR, "GOVERNANCE_DEPLOY_DIRECTORY_FAILED: " . $relative . "\n");
        exit(1);
    }
    $temp = tempnam($dir, '.governance-');
    if ($temp === false || !copy($from, $temp) || !chmod($temp, 0644)
        || !hash_equals(hash_file('sha256', $from), hash_file('sha256', $temp))
        || !link($temp, $to)) {
        if (is_string($temp)) @unlink($temp);
        fwrite(STDERR, "GOVERNANCE_DEPLOY_COPY_FAILED: " . $relative . "\n");
        exit(1);
    }
    unlink($temp);
    echo "GOVERNANCE_CODE_STAGED: " . $relative . "\n";
}
foreach ($files as $relative) {
    if (!is_file($root . '/' . $relative)
        || !hash_equals(hash_file('sha256', $stage . '/' . $relative), hash_file('sha256', $root . '/' . $relative))) {
        fwrite(STDERR, "GOVERNANCE_DEPLOY_VERIFICATION_FAILED: " . $relative . "\n");
        exit(1);
    }
}
echo "GOVERNANCE_CODE_INSTALLED_INACTIVE_NO_ROUTE_OR_FLAGS_CHANGED\n";
