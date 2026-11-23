<?php

declare(strict_types=1);

/**
 * Provision Maildir directories from an exported mailbox map.
 * Run as dedicated vmail user with a separately staged read-only map.\n * Never grant vmail access to gateway.sqlite or credential files.
 * Does not modify Postfix, Dovecot, DNS, or service state.
 */
$mapPath = (string) getenv('BREBO_MAILDIR_MAILBOX_MAP');
$root = rtrim((string) (getenv('BREBO_MAILDIR_ROOT') ?: '/var/vmail'), '/');
if ($mapPath === '' || !is_file($mapPath) || !is_readable($mapPath) || is_link($mapPath) ||\n    $root === '' || $root === '/' || !is_dir($root) || is_link($root)) {
  fwrite(STDERR, "MAILDIR_PROVISION=BLOCKED: invalid storage configuration\n");
  exit(2);
}
$stat = stat($root);
if ($stat === false || $stat['uid'] !== posix_geteuid() || posix_geteuid() === 0 || (($stat['mode'] & 0002) !== 0)) {
  fwrite(STDERR, "MAILDIR_PROVISION=BLOCKED: storage must belong to invoking non-root account\n");
  exit(2);
}
$lines = file($mapPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
  fwrite(STDERR, "MAILDIR_PROVISION=BLOCKED: mailbox map unreadable\n");
  exit(2);
}
$addresses = [];
foreach ($lines as $line) {
  $fields = preg_split('/\\s+/', trim($line));
  if (count($fields) !== 2 || $fields[1] !== '1') {
    fwrite(STDERR, "MAILDIR_PROVISION=BLOCKED: invalid mailbox map entry\n");
    exit(2);
  }
  $addresses[] = $fields[0];
}
if (count($addresses) !== count(array_unique($addresses))) {
  fwrite(STDERR, "MAILDIR_PROVISION=BLOCKED: duplicate mailboxes\n");
  exit(2);
}
if ($addresses === []) {
  fwrite(STDERR, "MAILDIR_PROVISION=BLOCKED: no mailboxes registered\n");
  exit(2);
}
$ensure = static function (string $path): void {
  if (is_link($path)) {
    throw new RuntimeException('Symlink in Maildir path');
  }
  if (!is_dir($path) && !mkdir($path, 0700)) {
    throw new RuntimeException('Unable to create Maildir directory');
  }
  $stat = stat($path);
  if (!is_dir($path) || $stat === false || $stat['uid'] !== posix_geteuid() || !chmod($path, 0700)) {
    throw new RuntimeException('Unsafe Maildir directory');
  }
};
try {
  foreach ($addresses as $address) {
    $address = strtolower((string) $address);
    if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
      throw new RuntimeException('Invalid mailbox address');
    }
    [$local, $domain] = explode('@', $address, 2);
    if (!preg_match('/^[a-z0-9][a-z0-9._+%-]*$/D', $local) ||
        !preg_match('/^[a-z0-9.-]+$/D', $domain) ||
        str_contains($local, '..') || str_contains($domain, '..')) {
      throw new RuntimeException('Unsafe mailbox path component');
    }
    $domainPath = $root . '/' . $domain;
    $mailboxPath = $domainPath . '/' . $local;
    $ensure($domainPath);
    $ensure($mailboxPath);
    foreach (['cur', 'new', 'tmp'] as $folder) {
      $ensure($mailboxPath . '/' . $folder);
    }
  }
} catch (Throwable $error) {
  fwrite(STDERR, "MAILDIR_PROVISION=BLOCKED: " . $error->getMessage() . "\n");
  exit(2);
}
echo "MAILDIR_PROVISION=PASS count=" . count($addresses) . "\n";
