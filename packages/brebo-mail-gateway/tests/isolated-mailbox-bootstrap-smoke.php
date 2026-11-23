<?php

declare(strict_types=1);

/**
 * Offline regression for the isolated mailbox bootstrap.
 * Uses a temporary SQLite database; never touches VPS mail services.
 */
$root = sys_get_temp_dir() . '/brebo-mail-bootstrap-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700)) {
  throw new RuntimeException('Cannot create temporary test directory');
}
$script = dirname(__DIR__) . '/bin/stage-isolated-test-mailbox.php';
$dbFile = $root . '/gateway.sqlite';
$cleanup = static function () use ($root, $dbFile): void {
  foreach ([$dbFile, $dbFile . '-wal', $dbFile . '-shm'] as $file) {
    if (is_file($file)) {
      unlink($file);
    }
  }
  rmdir($root);
};
try {
  touch($dbFile);
  $run = static function () use ($script, $root): array {
    $command = 'BREBO_MAIL_GATEWAY_DATA_DIR=' . escapeshellarg($root)
      . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
    exec($command . ' 2>&1', $output, $status);
    return [$status, implode("\n", $output)];
  };
  [$status, $output] = $run();
  if ($status !== 0 || !str_contains($output, 'TEST_MAILBOX=STAGED')) {
    throw new RuntimeException('First bootstrap failed: ' . $output);
  }
  [$status, $output] = $run();
  if ($status !== 0 || !str_contains($output, 'TEST_MAILBOX=STAGED')) {
    throw new RuntimeException('Idempotent bootstrap failed: ' . $output);
  }
  $pdo = new PDO('sqlite:' . $dbFile);
  foreach (['mail_domain' => 1, 'mailbox' => 1, 'mailbox_alias' => 0] as $table => $expected) {
    if ((int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== $expected) {
      throw new RuntimeException('Unexpected row count for ' . $table);
    }
  }
  $pdo->exec("INSERT INTO mailbox_alias(address,target_address,reference,changed_at)
    VALUES('other@testmail.brebobv.nl','test@testmail.brebobv.nl','test-alias',0)");
  [$status, $output] = $run();
  if ($status === 0 || !str_contains($output, 'TEST_MAILBOX=BLOCKED')) {
    throw new RuntimeException('Bootstrap did not reject pre-existing alias');
  }
  echo "TEST_MAILBOX_BOOTSTRAP_SMOKE=PASS\n";
} finally {
  $pdo = null;
  $cleanup();
}
