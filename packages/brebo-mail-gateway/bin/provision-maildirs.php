<?php

declare(strict_types=1);

/**
 * Provision Maildir directories for already registered gateway mailboxes.
 * Run as dedicated vmail user after creating /var/vmail securely.
 * Does not modify Postfix, Dovecot, DNS, or service state.
 */
$dataDir = rtrim((string) getenv('BREBO_MAIL_GATEWAY_DATA_DIR'), '/');
$root = rtrim((string) (getenv('BREBO_MAILDIR_ROOT') ?: '/var/vmail'), '/');
if ($dataDir === '' || $root === '' || $root === '/' || !is_dir($root) || is_link($root)) {
  fwrite(STDERR, "MAILDIR_PROVISION=BLOCKED: invalid storage configuration\n");
  exit(2);
}
$stat = stat($root);
if ($stat === false || $stat['uid'] !== posix_geteuid() || posix_geteuid() === 0 || (($stat['mode'] & 0002) !== 0)) {
  fwrite(STDERR, "MAILDIR_PROVISION=BLOCKED: storage must belong to invoking non-root account\n");
  exit(2);
}
$pdo = new PDO('sqlite:' . $dataDir . '/gateway.sqlite', null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$addresses = $pdo->query('SELECT address FROM mailbox ORDER BY address')->fetchAll(PDO::FETCH_COLUMN);
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
  if (!is_dir($path) || !chmod($path, 0700)) {
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
