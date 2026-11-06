<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
umask(0077);
function overviewTransform(string $source, array $chunks): string {
  foreach ($chunks as $chunk) {
    if (!is_string($chunk['before'] ?? null) || $chunk['before'] === '' || !is_string($chunk['after'] ?? null)
        || substr_count($source, $chunk['before']) !== 1) throw new RuntimeException('Reviewed source anchor missing');
    $source = str_replace($chunk['before'], $chunk['after'], $source);
  }
  return $source;
}
if (defined('OFFICE_OVERVIEW_LIBRARY_ONLY')) return;
define('OFFICE_BANK_ACTIVATION_LIBRARY_ONLY', true);
require __DIR__.'/office-moneybird-bank-activate.php';
$stage = dirname(__DIR__, 2);
$base = rtrim((string)getenv('HOME'), '/').'/domains/brebobv.nl/office_calc_test';
$configFile = $base.'/config/config.php';
$paths = ['src/Finance/OfficeFinanceView.php', 'public/office-finance.php'];
$journal = [];
$phase = 'scope';
try {
  if (($argv[1] ?? '') !== '--apply' || is_link($configFile) || realpath($configFile) !== $configFile) throw new RuntimeException('Unsafe configuration scope');
  $hash = hash_file('sha256', $configFile);
  $config = bankLoad($configFile);
  $root = $config['OFFICE_PRIVATE_ROOT'] ?? '';
  if (($config['OFFICE_BASE_PATH'] ?? '') !== '/office-test' || !is_string($root)
      || !str_starts_with($root, $base.'/release-') || !str_ends_with($root, '/private')
      || realpath($root) !== $root || ($config['OFFICE_MONEYBIRD_BANK_READ_ENABLED'] ?? '') !== 'enabled') throw new RuntimeException('Existing Office-test bank scope required');
  $changes = json_decode(file_get_contents(__DIR__.'/office-finance-overview-changes.json'), true, 32, JSON_THROW_ON_ERROR);
  if (array_keys($changes) !== $paths) throw new RuntimeException('Unexpected write scope');
  $phase = 'preview';
  $preview = $stage.'/overview-preview';
  if (file_exists($preview)) throw new RuntimeException('Preview already exists');
  mkdir($preview, 0700);
  foreach (['src/Finance', 'src/Intake', 'public', 'tests', 'backup/src/Finance', 'backup/public'] as $dir) mkdir($preview.'/'.$dir, 0700, true);
  foreach ($paths as $path) {
    $phase = $path === 'public/office-finance.php' ? 'controller_anchors' : 'view_anchors';
    $file = $root.'/'.$path;
    if (is_link($file) || realpath($file) !== $file) throw new RuntimeException('Unsafe active source');
    $source = file_get_contents($file);
    foreach ($changes[$path] as $index=>$chunk) echo 'OVERVIEW_ANCHOR_'.$phase.'_'.$index.'='.substr_count($source, $chunk['before'])."\n";
    $candidate = overviewTransform($source, $changes[$path]);
    file_put_contents($preview.'/'.$path, $candidate);
    file_put_contents($preview.'/backup/'.$path, $source);
    $journal[$path] = ['before'=>hash('sha256', $source), 'after'=>hash('sha256', $candidate), 'mode'=>fileperms($file)&0777];
  }
  foreach (['src/Finance/OfficeFinanceBankPanel.php', 'src/Intake/OfficeMount.php'] as $path) {
    $phase = 'preview_dependencies';
    if (is_link($root.'/'.$path) || realpath($root.'/'.$path) !== $root.'/'.$path) throw new RuntimeException('Unsafe preview dependency');
    copy($root.'/'.$path, $preview.'/'.$path);
  }
  foreach (['OfficeFinanceBankViewTest.php', 'OfficeFinanceOverviewTest.php'] as $test) copy($stage.'/tests/'.$test, $preview.'/tests/'.$test);
  foreach ($paths as $path) {
    $phase = 'preview_lint';
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($preview.'/'.$path).' 2>&1', $ignoredOutput, $status);
    if ($status !== 0) throw new RuntimeException('Preview syntax failed');
  }
  foreach (['OfficeFinanceBankViewTest.php', 'OfficeFinanceOverviewTest.php'] as $test) {
    $phase = $test === 'OfficeFinanceBankViewTest.php' ? 'preview_bank_test' : 'preview_overview_test';
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($preview.'/tests/'.$test).' 2>&1', $ignoredOutput, $status);
    if ($status !== 0) throw new RuntimeException('Preview contract failed');
  }
  if (hash_file('sha256', $configFile) !== $hash) throw new RuntimeException('Configuration changed during preview');
  foreach ($journal as $path=>$row) if (hash_file('sha256', $root.'/'.$path) !== $row['before']) throw new RuntimeException('Concurrent source change');
  file_put_contents($preview.'/JOURNAL.json', json_encode(['root'=>$root, 'files'=>$journal], JSON_THROW_ON_ERROR));
  $phase = 'install';
  foreach ($journal as $path=>$row) bankReplace($root.'/'.$path, $row['before'], file_get_contents($preview.'/'.$path), $row['mode']);
  if (hash_file('sha256', $configFile) !== $hash) throw new RuntimeException('Configuration changed during install');
  foreach ($journal as $path=>$row) if (hash_file('sha256', $root.'/'.$path) !== $row['after']) throw new RuntimeException('Installed source changed');
  echo "OFFICE_OVERVIEW_PREVIEW_TESTS_OK=1\nOFFICE_OVERVIEW_TWO_FILES_INSTALLED=1\nOFFICE_CONFIG_UNCHANGED=1\nOFFICE_DATABASE_NOT_OPENED=1\nOFFICE_OVERVIEW_INSTALL_OK=1\n";
} catch (Throwable $ignored) {
  echo 'OFFICE_OVERVIEW_FAILED_PHASE='.$phase."\n";
  if ($phase === 'install') {
    try {
      foreach ($journal as $path=>$row) bankRestore($root.'/'.$path, $row['before'], $row['after'], file_get_contents($preview.'/backup/'.$path), $row['mode']);
      echo "OFFICE_OVERVIEW_RESTORED=1\n";
    } catch (Throwable $ignoredRestore) { echo "OFFICE_OVERVIEW_RESTORE_REQUIRES_REVIEW=1\n"; }
  }
  exit(1);
}
