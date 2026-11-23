<?php

declare(strict_types=1);

/**
 * Provision an isolated test mailbox password without exposing it in argv.
 * The mailbox and its domain must already exist in gateway.sqlite.
 * Run as brebomail. Does not reload or change live mail services.
 */
$dataDir = rtrim((string) getenv('BREBO_MAIL_GATEWAY_DATA_DIR'), '/');
$address = strtolower(trim((string) ($argv[1] ?? '')));
if ($dataDir === '' || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
  fwrite(STDERR, "Usage: BREBO_MAIL_GATEWAY_DATA_DIR=... php provision-mailbox-password.php address@example.test < password-file\n");
  exit(2);
}
if (!defined('PASSWORD_ARGON2ID')) {
  fwrite(STDERR, "MAILBOX_PASSWORD=BLOCKED: Argon2id unavailable\n");
  exit(2);
}
$input = stream_get_contents(STDIN, 4096);
$password = rtrim((string) $input, "\r\n");
if (strlen($password) < 16 || strlen($password) > 1024) {
  fwrite(STDERR, "MAILBOX_PASSWORD=BLOCKED: password must be 16-1024 bytes\n");
  exit(2);
}
$pdo = new PDO('sqlite:' . $dataDir . '/gateway.sqlite', null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec('PRAGMA busy_timeout=5000');
$pdo->beginTransaction();
try {
  $check = $pdo->prepare('SELECT 1 FROM mailbox WHERE address = ?');
  $check->execute([$address]);
  if ($check->fetchColumn() === false) {
    throw new RuntimeException('Mailbox must be provisioned first');
  }
  $pdo->exec('CREATE TABLE IF NOT EXISTS mailbox_credential (
    address TEXT PRIMARY KEY,
    password_hash TEXT NOT NULL,
    changed_at INTEGER NOT NULL,
    FOREIGN KEY(address) REFERENCES mailbox(address)
  )');
  $hash = password_hash($password, PASSWORD_ARGON2ID);
  if (!is_string($hash)) {
    throw new RuntimeException('Password hashing failed');
  }
  $insert = $pdo->prepare('INSERT INTO mailbox_credential(address, password_hash, changed_at)
    VALUES(?, ?, ?) ON CONFLICT(address) DO UPDATE SET
    password_hash=excluded.password_hash, changed_at=excluded.changed_at');
  $insert->execute([$address, '{ARGON2ID}' . $hash, time()]);
  $pdo->commit();
  echo "MAILBOX_PASSWORD=STORED\n";
} catch (Throwable $error) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  fwrite(STDERR, "MAILBOX_PASSWORD=BLOCKED: " . $error->getMessage() . "\n");
  exit(2);
}
