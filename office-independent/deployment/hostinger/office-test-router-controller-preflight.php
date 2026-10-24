<?php
declare(strict_types=1);
/**
 * Read-only preflight for new Office-test write controllers.
 * Usage: php office-test-router-controller-preflight.php /absolute/path/to/private
 * No requests, configuration changes or database writes.
 */
$private = $argv[1] ?? '';
if (!is_string($private) || !is_dir($private) || !is_dir($private . '/public')) {
  fwrite(STDERR, "PRIVATE_RELEASE: ROOD\n");
  exit(1);
}
$controllers = [
  'INZET_DIENST' => 'office-inzet-shift-create.php',
  'INZET_PERSONEEL' => 'office-inzet-personnel-number.php',
];
$checks = [
  'POST_ONLY' => "REQUEST_METHOD",
  'ORIGIN_CHECK' => "HTTP_ORIGIN",
  'HOST_CHECK' => "OFFICE_PUBLIC_HOST",
  'HTTPS_CHECK' => "HTTPS",
  'SESSION_CHECK' => "currentOperator()",
  'CSRF_SECRET' => "OFFICE_TEST_INZET_CSRF_SECRET",
];
$failed = false;
foreach ($controllers as $name => $file) {
  $path = $private . '/public/' . $file;
  if (!is_file($path) || !is_readable($path)) {
    echo "$name: ONTBREEKT\n";
    $failed = true;
    continue;
  }
  $source = file_get_contents($path);
  if (!is_string($source)) {
    echo "$name: ONLEESBAAR\n";
    $failed = true;
    continue;
  }
  foreach ($checks as $label => $needle) {
    $ok = str_contains($source, $needle);
    echo "$name $label: " . ($ok ? 'GROEN' : 'ROOD') . PHP_EOL;
    $failed = $failed || !$ok;
  }
  $writeGate = $name === 'INZET_DIENST'
    ? 'OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED'
    : 'OFFICE_TEST_INZET_PERSONNEL_WRITE_ENABLED';
  $schemaGate = $name === 'INZET_DIENST'
    ? 'OFFICE_TEST_INZET_SHIFTS_SCHEMA_VERIFIED'
    : 'OFFICE_TEST_INZET_PERSONNEL_SCHEMA_VERIFIED';
  foreach (['WRITE_GATE' => $writeGate, 'SCHEMA_GATE' => $schemaGate] as $label => $needle) {
    $ok = str_contains($source, $needle);
    echo "$name $label: " . ($ok ? 'GROEN' : 'ROOD') . PHP_EOL;
    $failed = $failed || !$ok;
  }
}
echo 'CONTROLLER_PREFLIGHT: ' . ($failed ? 'ROOD' : 'GROEN') . PHP_EOL;
exit($failed ? 1 : 0);
