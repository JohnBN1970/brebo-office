<?php
declare(strict_types=1);
/**
 * Read-only authorization boundary audit for two newly routed Inzet writes.
 * Never executes controllers, config, or DB operations.
 * Usage: php office-test-auth-boundary-preflight.php /absolute/private/release
 */
$root = $argv[1] ?? '';
if (!is_string($root) || !is_dir($root . '/src/Inzet') || !is_dir($root . '/public')) {
  fwrite(STDERR, "PRIVATE_RELEASE: ROOD\n");
  exit(1);
}
$checks = [
  'DIENST_AUTH_CONTEXT' => ['src/Inzet/OfficeInzetAuthenticatedShiftAction.php', 'WebsiteCrmOperatorContext'],
  'DIENST_SERVER_SESSION' => ['src/Inzet/OfficeInzetAuthenticatedShiftAction.php', '$operator->sessionId'],
  'DIENST_SERVER_OPERATOR' => ['src/Inzet/OfficeInzetAuthenticatedShiftAction.php', '$operator->operatorId'],
  'DIENST_CSRF' => ['src/Inzet/OfficeInzetAuthenticatedShiftAction.php', 'OfficeInzetSessionCsrf::token'],
  'PERSONEEL_SERVER_SESSION' => ['src/Inzet/OfficeInzetPersonnelProfileAction.php', '$verifiedSessionId'],
  'PERSONEEL_SERVER_OPERATOR' => ['src/Inzet/OfficeInzetPersonnelProfileAction.php', '$verifiedOperatorId'],
  'PERSONEEL_CSRF' => ['src/Inzet/OfficeInzetPersonnelProfileAction.php', 'OfficeInzetPersonnelProfileCsrf::verify'],
  'PERSONEEL_REPOSITORY_AUTH' => ['src/Inzet/OfficeInzetPersonnelProfileAction.php', 'updatePersonnelNumber($verifiedOperatorId'],
  'DIENST_SESSION_PROVIDER' => ['public/office-inzet-shift-create.php', 'currentOperator()'],
  'PERSONEEL_SESSION_PROVIDER' => ['public/office-inzet-personnel-number.php', 'currentOperator()'],
];
$failed = false;
foreach ($checks as $label => [$path, $needle]) {
  $file = $root . '/' . $path;
  $source = is_readable($file) ? file_get_contents($file) : false;
  $ok = is_string($source) && str_contains($source, $needle);
  echo $label . ': ' . ($ok ? 'GROEN' : 'ROOD') . PHP_EOL;
  $failed = $failed || !$ok;
}
echo 'AUTH_BOUNDARY_PREFLIGHT: ' . ($failed ? 'ROOD' : 'GROEN') . PHP_EOL;
exit($failed ? 1 : 0);
