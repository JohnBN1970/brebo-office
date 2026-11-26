<?php
declare(strict_types=1);

/**
 * Strictly bounded CRM -> Calc feature activation for isolated BREBO Office-test VPS.
 * No migrations, grants, test records, production configuration or shared-hosting writes.
 * Manual root invocation: --plan (read-only) or --apply (explicit).
 */
if (PHP_SAPI !== 'cli' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) {
    fwrite(STDERR, "CRM_CALC_ACTIVATION=BLOCKED_ROOT_CLI_REQUIRED\n");
    exit(2);
}
$mode = $argv[1] ?? '';
if (!in_array($mode, ['--plan', '--apply'], true) || count($argv) !== 2) {
    fwrite(STDERR, "USAGE: php activate-crm-calc-test.php --plan|--apply\n");
    exit(2);
}
$fail = static function(string $reason): never {
    fwrite(STDERR, "CRM_CALC_ACTIVATION=BLOCKED_{$reason}\n");
    exit(1);
};
$rootPrivate = static function(string $path) use ($fail): string {
    clearstatcache(true, $path);
    if (!is_file($path) || is_link($path) || fileowner($path) !== 0 || (fileperms($path) & 0077) !== 0) {
        $fail('ROOT_PRIVATE_FILE');
    }
    $data = file_get_contents($path);
    if (!is_string($data)) $fail('READ_FAILED');
    return $data;
};
$release = realpath('/srv/brebo/office-test/current');
if (!is_string($release)
    || preg_match('~^/srv/brebo/office-test/releases/[a-f0-9]{40}$~D', $release) !== 1
    || trim((string)@file_get_contents($release.'/.office-source-sha')) !== basename($release)) {
    $fail('ISOLATED_RELEASE');
}
foreach ([
    'public/index.php',
    'public/office-calc-crm-start.php',
    'public/office-calc-crm-workbench.php',
    'public/office-crm-opportunities.php',
    'src/Crm/OfficeCrmFunnelViews.php',
    'deployment/vps/crm-calc-schema-preflight.php',
] as $required) {
    if (!is_file($release.'/'.$required)) $fail('MISSING_RELEASE_FILE_'.str_replace('/', '_', $required));
}
$board = file_get_contents($release.'/public/office-crm-opportunities.php');
if (!is_string($board) || !str_contains($board, 'Calculatie vanuit Deals is nog niet geactiveerd')
    || !str_contains($board, '$calcEnabled')) {
    $fail('CRM_BOARD_VISIBILITY_PATCH_NOT_DEPLOYED');
}
$poolPath = '/etc/php/8.3/fpm/pool.d/brebo-office-test.conf';
$original = $rootPrivate($poolPath);
if (preg_match('/^\[brebo-office-test\]\s*$/m', $original) !== 1
    || preg_match_all('/^\[[^\]]+\]/m', $original) !== 1
    || preg_match('/^env\[OFFICE_VPS_TEST_ROUTER_ENABLED\]\s*=\s*enabled\s*$/m', $original) !== 1
    || !is_file('/etc/brebo/office-test-db.env')) {
    $fail('ISOLATED_TEST_POOL');
}
$flags = ['OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED', 'OFFICE_CALC_CRM_SCHEMA_VERIFIED'];
foreach ($flags as $flag) {
    if (preg_match_all('/^\s*env\['.preg_quote($flag, '/').'\]\s*=/m', $original) > 1) {
        $fail('DUPLICATE_FLAG_'.$flag);
    }
}
$preflight = escapeshellarg($release.'/deployment/vps/crm-calc-schema-preflight.php');
$preflightOutput = [];
$preflightCode = -1;
exec('php '.$preflight.' --check 2>/dev/null', $preflightOutput, $preflightCode);
if ($preflightCode !== 0 || !in_array('CRM_CALC_SCHEMA_PREFLIGHT=SCHEMA_ONLY_PASS', $preflightOutput, true)) {
    $fail('SCHEMA_PREFLIGHT_NOT_PASS');
}
echo "CRM_CALC_TEST_RELEASE=".basename($release)."\n";
echo "CRM_CALC_SCHEMA_PREFLIGHT=PASS\n";
foreach ($flags as $flag) {
    $found = preg_match('/^env\['.preg_quote($flag, '/').'\]\s*=\s*(.*)$/m', $original, $m) === 1;
    echo $flag.'='.($found ? trim($m[1]) : 'MISSING')."\n";
}
if ($mode === '--plan') {
    echo "CRM_CALC_ACTIVATION=PLAN_ONLY_NO_WRITES\n";
    exit(0);
}
$new = $original;
foreach ($flags as $flag) {
    $line = 'env['.$flag.'] = enabled';
    $pattern = '/^env\['.preg_quote($flag, '/').'\]\s*=.*$/m';
    if (preg_match($pattern, $new)) {
        $new = preg_replace($pattern, $line, $new, 1);
        if (!is_string($new)) $fail('REPLACEMENT_FAILED');
    } else {
        $new = rtrim($new)."\n".$line."\n";
    }
}
if ($new === $original) {
    echo "CRM_CALC_ACTIVATION=ALREADY_ENABLED\n";
    exit(0);
}
$backup = $poolPath.'.crm-calc-backup-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
if (file_put_contents($backup, $original, LOCK_EX) === false || !chmod($backup, 0600)) {
    $fail('BACKUP_FAILED');
}
$temp = tempnam(dirname($poolPath), '.crm-calc-pool-');
if (!is_string($temp)) $fail('TEMPFILE_FAILED');
$oldMode = fileperms($poolPath) & 0777;
$owner = fileowner($poolPath);
$group = filegroup($poolPath);
if (!chmod($temp, 0600) || file_put_contents($temp, $new, LOCK_EX) === false
    || !chown($temp, $owner) || !chgrp($temp, $group)
    || !chmod($temp, $oldMode)) {
    @unlink($temp);
    $fail('STAGING_FAILED');
}
$restore = static function() use ($poolPath, $backup, $oldMode, $fail): never {
    $data = file_get_contents($backup);
    if (!is_string($data) || file_put_contents($poolPath, $data, LOCK_EX) === false
        || !chmod($poolPath, $oldMode)) {
        $fail('ROLLBACK_MANUAL_RECOVERY_REQUIRED');
    }
    exec('php-fpm8.3 -t >/dev/null 2>&1');
    exec('systemctl reload php8.3-fpm >/dev/null 2>&1');
    $fail('RELOAD_FAILED_CONFIG_RESTORED');
};
if (!rename($temp, $poolPath)) {
    @unlink($temp);
    $fail('CONFIG_RENAME_FAILED');
}
$checkCode = -1;
exec('php-fpm8.3 -t >/dev/null 2>&1', $output, $checkCode);
if ($checkCode !== 0) $restore();
$reloadCode = -1;
exec('systemctl reload php8.3-fpm >/dev/null 2>&1', $output, $reloadCode);
if ($reloadCode !== 0) $restore();
echo "CRM_CALC_ACTIVATION=TEST_FLAGS_ENABLED\n";
echo "CRM_CALC_POOL_BACKUP={$backup}\n";
echo "CRM_CALC_BROWSER_E2E=NOT_VERIFIED\n";
