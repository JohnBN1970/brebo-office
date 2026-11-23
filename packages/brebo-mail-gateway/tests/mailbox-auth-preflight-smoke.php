<?php

declare(strict_types=1);

/**
 * Isolated CLI regression test for the read-only mailbox auth preflight.
 * Never touches production mail configuration.
 */
$script = dirname(__DIR__) . '/bin/preflight-mailbox-auth.php';
$directory = sys_get_temp_dir() . '/brebo-mail-auth-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) {
  throw new RuntimeException('Cannot create isolated test directory');
}

$run = static function (string $mailboxes, string $users) use ($directory, $script): array {
  file_put_contents($directory . '/virtual_mailboxes', $mailboxes);
  file_put_contents($directory . '/users', $users);
  $command = 'BREBO_MAILSTACK_CONFIG_DIR=' . escapeshellarg($directory)
    . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1';
  exec($command, $output, $status);
  return [$status, implode("\n", $output)];
};

$hash = '{ARGON2ID}' . password_hash('test-only-long-password-1234', PASSWORD_ARGON2ID);
$cases = [
  'empty maps blocked' => ['', '', false],
  'placeholder blocked' => ["test@example.invalid 1\n", "test@example.invalid:*::::::\n", false],
  'missing user blocked' => ["test@example.invalid 1\n", '', false],
  'mismatched user blocked' => ["test@example.invalid 1\n", "other@example.invalid:$hash::::::\n", false],
  'duplicate mailbox blocked' => ["test@example.invalid 1\ntest@example.invalid 1\n", "test@example.invalid:$hash::::::\ntest@example.invalid:$hash::::::\n", false],
  'supported hash passes' => ["test@example.invalid 1\n", "test@example.invalid:$hash::::::\n", true],
];

try {
  foreach ($cases as $name => [$mailboxes, $users, $expected]) {
    [$status, $output] = $run($mailboxes, $users);
    $passed = ($status === 0);
    if ($passed !== $expected) {
      fwrite(STDERR, "FAIL: $name ($output)\n");
      exit(1);
    }
    echo "PASS: $name\n";
  }
} finally {
  @unlink($directory . '/virtual_mailboxes');
  @unlink($directory . '/users');
  @rmdir($directory);
}
