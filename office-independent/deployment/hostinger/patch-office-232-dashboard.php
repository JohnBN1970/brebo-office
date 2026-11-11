<?php
declare(strict_types=1);

/**
 * Guarded, additive installation of 232-home demonstration planning.
 * Does not replace the server dossier page or modify database records.
 */
if (PHP_SAPI !== 'cli' || count($argv) !== 4) {
    fwrite(STDERR, "DEMO_232_ARGUMENTS_INVALID\n");
    exit(2);
}
[, $beforePath, $afterPath, $livePath] = $argv;
$base = realpath(dirname($livePath));
$expected = realpath((string) getenv('HOME') . '/domains/brebobv.nl/office_calc_test/release-office-test-contractform-20261030/private/public');
if ($base === false || $expected === false || $base !== $expected || is_link($livePath) || !is_file($livePath)) {
    fwrite(STDERR, "DEMO_232_SCOPE_REJECTED\n");
    exit(3);
}
$before = file_get_contents($beforePath);
$after = file_get_contents($afterPath);
$live = file_get_contents($livePath);
if ($before === false || $after === false || $live === false) {
    fwrite(STDERR, "DEMO_232_SOURCE_UNREADABLE\n");
    exit(4);
}
if ($live === $after) {
    echo "DEMO_232_ALREADY_INSTALLED\n";
    exit(0);
}
$anchor = '$demoDashboardHtml = ' . chr(39) . chr(39) . ';';
$next = 'if ($isDashboardDemo) {';
$start = strpos($after, $anchor);
$end = $start !== false ? strpos($after, $next, $start) : false;
if ($start === false || $end === false || $end <= $start) {
    fwrite(STDERR, "DEMO_232_EXTENSION_NOT_FOUND\n");
    exit(5);
}
$extension = substr($after, $start, $end - $start);
if (strpos($extension, 'is232Demo') === false || strpos($extension, 'Planning uit Office-test') === false
    || substr_count($before, $anchor) !== 1 || substr_count($live, $anchor) !== 1) {
    fwrite(STDERR, "DEMO_232_EXTENSION_INVALID\n");
    exit(6);
}
if (strpos($live, 'Planning uit Office-test') !== false) {
    echo "DEMO_232_ALREADY_PRESENT_MANUAL_REVIEW\n";
    exit(7);
}
$oldTail = "    . '</div>';\n}\n" . '$dashboardHtml =';
$newTail = "    . " . '$demoPlanHtml' . "\n    . '</div>';\n}\n" . '$dashboardHtml =';
if (substr_count($before, $oldTail) !== 1
    || substr_count($after, $newTail) !== 1
    || substr_count($live, $oldTail) !== 1) {
    fwrite(STDERR, "DEMO_232_STRUCTURE_CHANGED_ABORT\n");
    exit(8);
}
$patched = str_replace($anchor, $extension, $live);
$patched = str_replace($oldTail, $newTail, $patched);
if ($patched === $live || substr_count($patched, 'Planning uit Office-test') !== 1) {
    fwrite(STDERR, "DEMO_232_PATCH_UNSAFE\n");
    exit(9);
}
$dir = dirname($livePath);
$temp = tempnam($dir, '.demo232-');
if ($temp === false) {
    fwrite(STDERR, "DEMO_232_TEMP_FAILURE\n");
    exit(10);
}
try {
    if (file_put_contents($temp, $patched) === false) {
        throw new RuntimeException('WRITE_FAILED');
    }
    chmod($temp, fileperms($livePath) & 0777);
    $lint = [];
    $lintCode = 1;
    exec('php -l ' . escapeshellarg($temp) . ' 2>&1', $lint, $lintCode);
    if ($lintCode !== 0) {
        throw new RuntimeException('LINT_FAILED: ' . implode(' ', $lint));
    }
    // Recheck concurrent changes between reading the original and final rename.
    if (file_get_contents($livePath) !== $live) {
        throw new RuntimeException('CONCURRENT_CHANGE_ABORT');
    }
    $backup = $livePath . '.backup-232-' . gmdate('YmdHis');
    if (!copy($livePath, $backup)) {
        throw new RuntimeException('BACKUP_FAILED');
    }
    if (!rename($temp, $livePath)) {
        throw new RuntimeException('RENAME_FAILED');
    }
    echo "DEMO_232_PATCHED_OFFICE_TEST\n";
} catch (Throwable $exception) {
    @unlink($temp);
    fwrite(STDERR, "DEMO_232_" . $exception->getMessage() . "\n");
    exit(11);
}
