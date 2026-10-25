<?php
declare(strict_types=1);
/** Read-only CLI diagnostics for isolated Office-test router; never sends POST. */
$candidate = $argv[1] ?? '';
if (!is_string($candidate) || !is_readable($candidate)) {
  echo "CANDIDATE_FILE: ROOD (bestand ontbreekt of is niet leesbaar)\n";
  exit(2);
}
echo "CANDIDATE_FILE: GROEN\n";
$cases = [
  'UNKNOWN_ROUTE' => '/office-test/__router_preflight_missing_20261024',
  'DIENST_GET' => '/office-test/inzet/dienst/nieuw',
  'PERSONEEL_GET' => '/office-test/inzet/medewerker/personeelsnummer',
];
$failed = false;
foreach ($cases as $label => $path) {
  $script = <<<'PHP'
<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $argv[2];
$_SERVER['HTTP_HOST'] = 'brebobv.nl';
$_SERVER['HTTPS'] = 'on';
register_shutdown_function(static function (): void {
  $status = http_response_code();
  fwrite(STDERR, 'OFFICE_CLI_STATUS=' . (is_int($status) ? $status : 0) . PHP_EOL);
});
require $argv[1];
PHP;
  $tmp = tempnam(sys_get_temp_dir(), 'office-cli-');
  if ($tmp === false || file_put_contents($tmp, $script) === false) {
    echo "TEMP_FILE: ROOD\n";
    exit(2);
  }
  $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' '
    . escapeshellarg($candidate) . ' ' . escapeshellarg($path);
  $output = [];
  $exit = 0;
  exec($cmd . ' 2>&1', $output, $exit);
  unlink($tmp);
  $status = null;
  foreach ($output as $line) {
    if (preg_match('/OFFICE_CLI_STATUS=(\\d+)\\s*$/', $line, $m)) $status = (int)$m[1];
  }
  $expected = $label === 'UNKNOWN_ROUTE' ? [404] : ($label === 'DIENST_GET' ? [405,503] : [405]);
  $ok = $exit === 0 && in_array($status, $expected, true);
  echo $label . ': ' . ($ok ? 'GROEN' : 'ROOD') . ' (CLI-status '
    . ($status === null ? 'onbekend' : $status) . ', exit ' . $exit . ')' . PHP_EOL;
  $failed = $failed || !$ok;
}
echo 'CANDIDATE_CLI_SMOKE: ' . ($failed ? 'ROOD' : 'GROEN') . PHP_EOL;
exit($failed ? 1 : 0);
