<?php
declare(strict_types=1);
/**
 * Isolated CLI smoke test. No web exposure, POST, credentials or DB writes.
 * Runs the candidate router in a child PHP process for each GET request.
 * Usage: php office-test-candidate-cli-smoke.php /tmp/office-test-router-candidate.php
 */
$candidate = $argv[1] ?? '';
if (!is_string($candidate) || !is_file($candidate) || !is_readable($candidate)) {
  fwrite(STDERR, "CANDIDATE: ONTBREEKT\n");
  exit(2);
}
$cases = [
  '/office-test/__router_preflight_missing_20261024' => 404,
  '/office-test/inzet/dienst/nieuw' => 503,
  '/office-test/inzet/medewerker/personeelsnummer' => 405,
];
$failed = false;
foreach ($cases as $path => $expected) {
  $bootstrap = <<<'PHP'
<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $argv[2];
$_SERVER['HTTP_HOST'] = 'brebobv.nl';
$_SERVER['HTTPS'] = 'on';
require $argv[1];
PHP;
  $tmp = tempnam(sys_get_temp_dir(), 'office-route-');
  if ($tmp === false || file_put_contents($tmp, $bootstrap) === false) {
    fwrite(STDERR, "TEMP: ROOD\n");
    exit(2);
  }
  $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp)
    . ' ' . escapeshellarg($candidate) . ' ' . escapeshellarg($path);
  $output = [];
  $exitCode = 0;
  exec($command . ' 2>&1', $output, $exitCode);
  unlink($tmp);
  // CLI SAPI does not expose http_response_code() after a child process exits.
  // Check only the known fail-closed response text; never claim HTTP status.
  $body = implode("\n", $output);
  $needles = [
    404 => 'Testpagina niet gevonden.',
    503 => 'Dienstinvoer niet beschikbaar.',
    405 => '',
  ];
  $ok = $exitCode === 0 && $expected !== 405
    && str_contains($body, $needles[$expected]);
  if ($expected === 405) {
    // The personnel controller exits silently for GET, so this cannot prove 405.
    $ok = $exitCode === 0 && trim($body) === '';
  }
  echo $path . ': ' . ($ok ? 'GROEN' : 'ROOD') . ' (CLI-respons, geen HTTP-status)' . PHP_EOL;
  if (!$ok) {
    // Do not print child output: it may include private paths or runtime details.
    $failed = true;
  }
}
echo 'CANDIDATE_CLI_SMOKE: ' . ($failed ? 'ROOD' : 'GROEN') . PHP_EOL;
exit($failed ? 1 : 0);
