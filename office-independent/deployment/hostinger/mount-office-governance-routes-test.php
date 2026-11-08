<?php
declare(strict_types=1);
/** Explicit route-only Office-test mount. Does not touch config, DB, grants, or existing handlers. */
if (PHP_SAPI !== 'cli' || getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_GOVERNANCE_ROUTER_DEPLOY') !== 'explicit') {
    fwrite(STDERR, "GOVERNANCE_ROUTE_SCOPE_DENIED\n");
    exit(2);
}
$base = '/home/u213420663/domains/brebobv.nl/office_calc_test';
$configFile = $base . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "GOVERNANCE_ROUTE_CONFIG_MISSING\n");
    exit(2);
}
$config = require $configFile;
$active = is_array($config) && ($config['OFFICE_BASE_PATH'] ?? null) === '/office-test'
    && is_string($config['OFFICE_PRIVATE_ROOT'] ?? null)
    ? realpath($config['OFFICE_PRIVATE_ROOT']) : false;
$realBase = realpath($base);
if (!is_string($active) || !is_string($realBase)
    || !str_starts_with($active, $realBase . DIRECTORY_SEPARATOR)
    || preg_match('~/release-[^/]+/private$~D', $active) !== 1
    || !is_file($active . '/public/office-governance.php')) {
    fwrite(STDERR, "GOVERNANCE_ROUTE_HANDLER_NOT_READY\n");
    exit(2);
}
$webroot = '/home/u213420663/domains/brebobv.nl/public_html/office-test';
$router = $webroot . '/index.php';
$rewrite = $webroot . '/.htaccess';
foreach ([$router, $rewrite] as $path) {
    if (!is_file($path) || is_link($path) || !is_readable($path) || !is_writable($path)) {
        fwrite(STDERR, "GOVERNANCE_ROUTE_TARGET_UNSAFE\n");
        exit(2);
    }
}
$lock = fopen($base . '/.governance-router.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX)) {
    fwrite(STDERR, "GOVERNANCE_ROUTE_LOCK_FAILED\n");
    exit(2);
}
$originalRouter = file_get_contents($router);
$originalRewrite = file_get_contents($rewrite);
if (!is_string($originalRouter) || !is_string($originalRewrite)) {
    fwrite(STDERR, "GOVERNANCE_ROUTE_READ_FAILED\n");
    exit(2);
}
$routes = [
    "  '/office-test/kwaliteit' => 'office-governance.php',",
    "  '/office-test/risicos' => 'office-governance.php',",
    "  '/office-test/inkoop' => 'office-governance.php',",
];
$rewriteRule = 'RewriteRule ^(?:kwaliteit|risicos|inkoop)/?$ index.php [END,QSA]';
$present = array_map(static fn(string $route): bool => str_contains($originalRouter, $route), $routes);
$count = count(array_filter($present));
$hasRewrite = str_contains($originalRewrite, $rewriteRule);
if ($count === 3 && $hasRewrite) {
    echo "GOVERNANCE_ROUTES_ALREADY_MOUNTED_INACTIVE\n";
    exit(0);
}
if ($count !== 0 || $hasRewrite) {
    fwrite(STDERR, "GOVERNANCE_ROUTE_PARTIAL_STATE_REQUIRES_REVIEW\n");
    exit(2);
}
$anchor = "  '/office-test/gebouwen' => 'office-buildings.php',";
if (substr_count($originalRouter, $anchor) !== 1
    || substr_count($originalRewrite, 'RewriteEngine On') !== 1
    || preg_match('/(?m)^\s*RewriteRule\s+\^.*(?:kwaliteit|risicos|inkoop)/', $originalRewrite) === 1) {
    fwrite(STDERR, "GOVERNANCE_ROUTE_ANCHOR_OR_REWRITE_CONFLICT\n");
    exit(2);
}
$newRouter = str_replace($anchor, $anchor . "\n" . implode("\n", $routes), $originalRouter);
$newRewrite = str_replace('RewriteEngine On',
    "RewriteEngine On\n# BREBO native governance Office-test routes\n" . $rewriteRule,
    $originalRewrite);
$backupDir = $base . '/private-backups/governance';
if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) {
    fwrite(STDERR, "GOVERNANCE_ROUTE_BACKUP_DIR_FAILED\n");
    exit(2);
}
chmod($backupDir, 0700);
$stamp = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(5));
$backupRouter = $backupDir . '/index-before-governance-' . $stamp . '.php';
$backupRewrite = $backupDir . '/htaccess-before-governance-' . $stamp . '.txt';
if (file_put_contents($backupRouter, $originalRouter, LOCK_EX) !== strlen($originalRouter)
    || file_put_contents($backupRewrite, $originalRewrite, LOCK_EX) !== strlen($originalRewrite)
    || !chmod($backupRouter, 0600) || !chmod($backupRewrite, 0600)) {
    fwrite(STDERR, "GOVERNANCE_ROUTE_BACKUP_FAILED\n");
    exit(2);
}
$tmpRouter = tempnam($webroot, '.governance-router-');
$tmpRewrite = tempnam($webroot, '.governance-rewrite-');
if (!is_string($tmpRouter) || !is_string($tmpRewrite)) {
    fwrite(STDERR, "GOVERNANCE_ROUTE_TEMP_FAILED\n");
    exit(2);
}
try {
    if (file_put_contents($tmpRouter, $newRouter, LOCK_EX) !== strlen($newRouter)
        || file_put_contents($tmpRewrite, $newRewrite, LOCK_EX) !== strlen($newRewrite)) {
        throw new RuntimeException('write_candidate');
    }
    chmod($tmpRouter, fileperms($router) & 0777);
    chmod($tmpRewrite, fileperms($rewrite) & 0777);
    $syntax = [];
    $exit = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmpRouter) . ' 2>&1', $syntax, $exit);
    if ($exit !== 0) {
        throw new RuntimeException('php_syntax');
    }
    if (!rename($tmpRewrite, $rewrite)) {
        throw new RuntimeException('rewrite_install');
    }
    if (!rename($tmpRouter, $router)) {
        copy($backupRewrite, $rewrite);
        throw new RuntimeException('router_install');
    }
    if (file_get_contents($router) !== $newRouter || file_get_contents($rewrite) !== $newRewrite) {
        copy($backupRouter, $router);
        copy($backupRewrite, $rewrite);
        throw new RuntimeException('verify_after_install');
    }
    echo "GOVERNANCE_ROUTER_MOUNTED_FLAGS_UNCHANGED\n";
    echo "GOVERNANCE_ROUTER_BACKUP_CREATED\n";
} catch (Throwable $e) {
    fwrite(STDERR, "GOVERNANCE_ROUTE_FAILED_PHASE=" . $e->getMessage() . "\n");
    exit(1);
} finally {
    if (is_file($tmpRouter)) unlink($tmpRouter);
    if (is_file($tmpRewrite)) unlink($tmpRewrite);
}
