<?php

declare(strict_types=1);

use Brebo\MailGateway\Infrastructure\SqliteGatewayProvisioningRepository;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

/**
 * Stage a single isolated test domain and mailbox in the gateway database.
 * No DNS, mail transport, credentials, service reloads, or MX changes.
 */
$domain = 'testmail.brebobv.nl';
$address = 'test@' . $domain;
$dataDir = (string) getenv('BREBO_MAIL_GATEWAY_DATA_DIR');
if ($dataDir === '' || !is_dir($dataDir) || is_link($dataDir)) {
  fwrite(STDERR, "TEST_MAILBOX=BLOCKED: invalid gateway data directory\n");
  exit(2);
}
$dbFile = rtrim($dataDir, '/') . '/gateway.sqlite';
if (!is_file($dbFile) || is_link($dbFile) || !is_readable($dbFile) || !is_writable($dbFile)) {
  fwrite(STDERR, "TEST_MAILBOX=BLOCKED: existing writable gateway database required\n");
  exit(2);
}
try {
  $pdo = new PDO('sqlite:' . $dbFile, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
  $repo = new SqliteGatewayProvisioningRepository($pdo);
  $pdo->beginTransaction();
  $existingDomains = $repo->domains();
  $existingMailboxes = $repo->mailboxes();
  $existingAliases = $repo->aliases();
  foreach ($existingDomains as $row) {
    if (($row['domain'] ?? '') !== $domain) {
      throw new RuntimeException('Other domains already registered; refusing isolated bootstrap');
    }
  }
  foreach ($existingMailboxes as $row) {
    if (($row['address'] ?? '') !== $address) {
      throw new RuntimeException('Other mailboxes already registered; refusing isolated bootstrap');
    }
  }
  if ($existingAliases !== []) {
    throw new RuntimeException('Aliases already registered; refusing isolated bootstrap');
  }
  $repo->provisionDomain(['domain' => $domain, 'test_only' => true]);
  $repo->provisionMailbox(['address' => $address, 'test_only' => true]);
  $pdo->commit();
  echo "TEST_MAILBOX=STAGED domain=$domain address=$address\n";
  echo "MAIL_TRANSPORT=UNCHANGED DNS=UNCHANGED AUTH=NOT_CONFIGURED\n";
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  fwrite(STDERR, "TEST_MAILBOX=BLOCKED: " . $e->getMessage() . "\n");
  exit(2);
}
