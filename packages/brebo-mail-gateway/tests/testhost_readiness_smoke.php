<?php

declare(strict_types=1);

$script = dirname(__DIR__) . '/bin/testhost-readiness.php';

$run = static function (array $env) use ($script): array {
  $descriptor = [
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
  ];
  $process = proc_open([PHP_BINARY, $script], $descriptor, $pipes, NULL, $env);
  if (!is_resource($process)) {
    throw new RuntimeException('Readiness smoke kon niet starten.');
  }
  $stdout = stream_get_contents($pipes[1]) ?: '';
  $stderr = stream_get_contents($pipes[2]) ?: '';
  fclose($pipes[1]);
  fclose($pipes[2]);
  return ['code' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
};

$ok = $run([
  'BREBO_MAIL_TEST_DOMAINS' => 'mail-test.example.nl',
  'BREBO_MAIL_PROTECTED_DOMAINS' => 'brebobv.nl',
  'BREBO_MAILSTACK_RELOAD_ENABLED' => '0',
]);
if ($ok['code'] !== 0 || !str_contains($ok['stdout'], 'BREBO_MAIL_TESTHOST_READINESS=PASS')) {
  throw new RuntimeException('Valid testhost readiness failed.');
}

$production = $run([
  'BREBO_MAIL_TEST_DOMAINS' => 'brebobv.nl',
  'BREBO_MAIL_PROTECTED_DOMAINS' => '',
  'BREBO_MAILSTACK_RELOAD_ENABLED' => '0',
]);
if ($production['code'] === 0 || !str_contains($production['stderr'], 'Protected production domain')) {
  throw new RuntimeException('Protected production domain was not blocked.');
}

$reload = $run([
  'BREBO_MAIL_TEST_DOMAINS' => 'mail-test.example.nl',
  'BREBO_MAILSTACK_RELOAD_ENABLED' => '1',
]);
if ($reload['code'] === 0 || !str_contains($reload['stderr'], 'moet 0 blijven')) {
  throw new RuntimeException('Testhost readiness must reject enabled reload.');
}

echo "BREBO_MAIL_TESTHOST_READINESS_SMOKE=PASS\n";
