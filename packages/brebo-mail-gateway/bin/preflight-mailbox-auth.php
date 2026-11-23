<?php

declare(strict_types=1);

/**
 * Read-only activation gate for virtual mailbox authentication.
 *
 * Generated users containing placeholder credentials must never be
 * activated as a Dovecot passwd-file.
 */
$directory = rtrim((string) getenv('BREBO_MAILSTACK_CONFIG_DIR'), DIRECTORY_SEPARATOR);
if ($directory === '' || !is_dir($directory)) {
  fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: configuration directory missing\n");
  exit(2);
}

$users = $directory . '/users';
$mailboxes = $directory . '/virtual_mailboxes';
if (!is_readable($users) || !is_readable($mailboxes)) {
  fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: generated maps missing\n");
  exit(2);
}

$mailboxLines = array_values(array_filter(array_map('trim', file($mailboxes, FILE_IGNORE_NEW_LINES) ?: [])));
$userLines = array_values(array_filter(array_map('trim', file($users, FILE_IGNORE_NEW_LINES) ?: [])));

if ($mailboxLines === [] || $userLines === []) {
  fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: no test mailboxes provisioned\n");
  exit(2);
}

if (count($mailboxLines) !== count($userLines)) {
  fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: mailbox/user count mismatch\n");
  exit(2);
}

$expected = [];\nforeach ($mailboxLines as $line) {\n  $parts = preg_split('/\\s+/', $line);\n  $address = strtolower((string) ($parts[0] ?? ''));\n  if (!filter_var($address, FILTER_VALIDATE_EMAIL) || isset($expected[$address])) {\n    fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: invalid or duplicate mailbox map\\n");\n    exit(2);\n  }\n  $expected[$address] = true;\n}\n\n$seen = [];\nforeach ($userLines as $line) {
  $fields = explode(':', $line);
  $address = strtolower((string) ($fields[0] ?? ''));\n  if (!isset($expected[$address]) || isset($seen[$address])) {\n    fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: unexpected or duplicate user\\n");\n    exit(2);\n  }\n  $seen[$address] = true;\n  $password = $fields[1] ?? '';
  if (count($fields) < 2 || $password === '' || $password === '*' || $password === '!' || !str_starts_with($password, '{')) {
    fwrite(STDERR, "MAIL_AUTH_PREFLIGHT=BLOCKED: missing usable password hash\n");
    exit(2);
  }
}

echo "MAIL_AUTH_PREFLIGHT=PASS\n";
