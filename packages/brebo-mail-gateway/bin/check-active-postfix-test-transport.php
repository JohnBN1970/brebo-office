<?php

declare(strict_types=1);

/**
 * Read-only comparison of generated test-domain routing with active Postfix.
 * No postconf -e, master.cf writes, reload or MX changes.
 */
$dir = rtrim((string) getenv('BREBO_MAILSTACK_CONFIG_DIR'), DIRECTORY_SEPARATOR);
if ($dir === '' || !is_file($dir . '/transport_map')) {
  fwrite(STDERR, "Generated transport map is missing.\n");
  exit(2);
}

function postfixRead(string $command, array $args): string {
  $escaped = array_map('escapeshellarg', $args);
  $lines = [];
  $status = 0;
  exec(escapeshellcmd($command) . ' ' . implode(' ', $escaped) . ' 2>&1', $lines, $status);
  if ($status !== 0) {
    throw new RuntimeException($command . ' failed: ' . implode("\n", $lines));
  }
  return trim(implode("\n", $lines));
}

try {
  $maps = postfixRead('postconf', ['-h', 'transport_maps']);
  $services = postfixRead('postconf', ['-M']);
  $expected = 'hash:' . $dir . '/transport_map';
  $activeMap = in_array($expected, array_map('trim', preg_split('/[\\s,]+/', $maps) ?: []), TRUE);
  $activePipe = (bool) preg_match('/^brebo_inbound\\s+unix\\s+.*\\bpipe\\s*$/m', $services);
  echo 'BREBO_POSTFIX_TEST_MAP_ACTIVE=' . ($activeMap ? 'YES' : 'NO') . PHP_EOL;
  echo 'BREBO_POSTFIX_TEST_PIPE_ACTIVE=' . ($activePipe ? 'YES' : 'NO') . PHP_EOL;
  if (!$activeMap || !$activePipe) {
    fwrite(STDERR, "Generated fragments are not fully active in Postfix.\n");
    exit(1);
  }
  echo "BREBO_POSTFIX_TEST_ACTIVATION=PASS\n";
}
catch (Throwable $error) {
  fwrite(STDERR, $error->getMessage() . PHP_EOL);
  exit(2);
}
