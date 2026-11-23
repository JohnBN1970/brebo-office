<?php

declare(strict_types=1);

/**
 * Render Dovecot passwd-file from provisioned Argon2id hashes.
 * Run after render-mailstack-config.php, before auth preflight.
 * Fails closed if any mailbox lacks credentials.
 */
$dataDir = rtrim((string) getenv('BREBO_MAIL_GATEWAY_DATA_DIR'), '/');
$outputDir = rtrim((string) getenv('BREBO_MAILSTACK_CONFIG_DIR'), '/');
if ($dataDir === '' || $outputDir === '' || !is_dir($outputDir)) {
  fwrite(STDERR, "DOVECOT_USERS=BLOCKED: missing configured directories\n");
  exit(2);
}
$pdo = new PDO('sqlite:' . $dataDir . '/gateway.sqlite', null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$table = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='mailbox_credential'")->fetchColumn();
if (!$table) {
  fwrite(STDERR, "DOVECOT_USERS=BLOCKED: no credential table\n");
  exit(2);
}
$rows = $pdo->query('SELECT m.address, c.password_hash FROM mailbox m
  LEFT JOIN mailbox_credential c ON c.address=m.address ORDER BY m.address')->fetchAll(PDO::FETCH_ASSOC);
if ($rows === []) {
  fwrite(STDERR, "DOVECOT_USERS=BLOCKED: no mailboxes\n");
  exit(2);
}
$lines = [];
foreach ($rows as $row) {
  $address = (string) $row['address'];
  $hash = (string) ($row['password_hash'] ?? '');
  if (!filter_var($address, FILTER_VALIDATE_EMAIL) ||
      !preg_match('/^\\{ARGON2ID\\}\\$argon2id\\$/', $hash)) {
    fwrite(STDERR, "DOVECOT_USERS=BLOCKED: missing valid credential\n");
    exit(2);
  }
  $lines[] = $address . ':' . $hash . '::::::';
}
$path = $outputDir . '/users';
$temp = tempnam($outputDir, '.users-');
if ($temp === false) {
  fwrite(STDERR, "DOVECOT_USERS=BLOCKED: cannot create temporary file\n");
  exit(2);
}
try {
  if (file_put_contents($temp, implode("\n", $lines) . "\n", LOCK_EX) === false ||
      !chmod($temp, 0600) || !rename($temp, $path)) {
    throw new RuntimeException('Cannot publish users file');
  }
} catch (Throwable $error) {
  @unlink($temp);
  fwrite(STDERR, "DOVECOT_USERS=BLOCKED: publish failed\n");
  exit(2);
}
echo "DOVECOT_USERS=RENDERED count=" . count($lines) . "\n";
