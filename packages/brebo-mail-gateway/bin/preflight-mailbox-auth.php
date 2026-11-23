<?php

declare(strict_types=1);

/**
 * Fail-closed, read-only preflight for generated Dovecot passwd-file.
 * Never outputs credentials or their hashes.
 */
$directory = rtrim((string) getenv('BREBO_MAILSTACK_CONFIG_DIR'), DIRECTORY_SEPARATOR);
if ($directory === '' || !is_dir($directory)) {
  fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: configuration directory missing\n");
  exit(2);
}

$usersPath = $directory . '/users';
$mailboxesPath = $directory . '/virtual_mailboxes';
if (!is_readable($usersPath) || !is_readable($mailboxesPath)) {
  fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: generated maps missing\n");
  exit(2);
}

$readLines = static function (string $path): array {
  $lines = file($path, FILE_IGNORE_NEW_LINES);
  if ($lines === false) {
    throw new RuntimeException('Unable to read generated map');
  }
  return array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
};

try {
  $mailboxLines = $readLines($mailboxesPath);
  $userLines = $readLines($usersPath);
} catch (RuntimeException $exception) {
  fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: unable to read maps\n");
  exit(2);
}

if ($mailboxLines === [] || $userLines === []) {
  fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: no test mailboxes provisioned\n");
  exit(2);
}
if (count($mailboxLines) !== count($userLines)) {
  fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: mailbox/user count mismatch\n");
  exit(2);
}

$expected = [];
foreach ($mailboxLines as $line) {
  $parts = preg_split('/\\s+/', $line);
  $address = strtolower((string) ($parts[0] ?? ''));
  if (count($parts) !== 2 || !filter_var($address, FILTER_VALIDATE_EMAIL) || isset($expected[$address])) {
    fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: invalid or duplicate mailbox map\n");
    exit(2);
  }
  $expected[$address] = true;
}

$seen = [];
foreach ($userLines as $line) {
  $fields = explode(':', $line);
  $address = strtolower((string) ($fields[0] ?? ''));
  if (count($fields) < 2 || !isset($expected[$address]) || isset($seen[$address])) {
    fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: unexpected or duplicate user\n");
    exit(2);
  }
  $seen[$address] = true;
  $password = (string) $fields[1];
  if (!preg_match('/^\\{(ARGON2ID|BLF-CRYPT|SHA512-CRYPT)\\}[^\\s:]{20,}$/', $password)) {
    fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: missing supported password hash\n");
    exit(2);
  }
}

echo "MAIL_AUTH_PREFLIGHT=PASS\n";
