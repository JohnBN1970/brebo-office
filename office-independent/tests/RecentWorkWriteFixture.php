<?php

declare(strict_types=1);

/**
 * Explicit destructive fixture for the isolated Office TEST database only.
 * Inserts disposable random operators, verifies five-item retention and
 * cross-operator separation, then removes all fixture rows.
 * Never run against production. Requires a separate opt-in from read-only smoke.
 */
if (getenv('OFFICE_RECENT_WORK_WRITE_ACK') !== 'isolated-write-test') {
  fwrite(STDERR, "Refusing write fixture: explicit isolated-write-test acknowledgement required.\n");
  exit(2);
}
$dsn = getenv('OFFICE_RECENT_WORK_TEST_DSN');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) {
  fwrite(STDERR, "Refusing write fixture: MySQL test DSN required.\n");
  exit(2);
}
require_once dirname(__DIR__) . '/src/RecentWork/RecentWorkEvent.php';
require_once dirname(__DIR__) . '/src/RecentWork/PdoRecentWorkRepository.php';

$db = new PDO(
  $dsn,
  getenv('OFFICE_RECENT_WORK_TEST_USER') ?: '',
  getenv('OFFICE_RECENT_WORK_TEST_PASSWORD') ?: '',
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$dbName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
if (!preg_match('/(?:^|_)test(?:_|$)|(?:^|_)staging(?:_|$)/i', $dbName)) {
  throw new RuntimeException('Refusing writes outside an explicitly named test/staging database.');
}
if (getenv('OFFICE_RECENT_WORK_WRITE_DB') !== $dbName) {
  throw new RuntimeException('Refusing writes: exact test database name acknowledgement required.');
}

use Brebo\Office\RecentWork\PdoRecentWorkRepository;
use Brebo\Office\RecentWork\RecentWorkEvent;

$repo = new PdoRecentWorkRepository($db);
$a = 'fixture-a-' . bin2hex(random_bytes(12));
$b = 'fixture-b-' . bin2hex(random_bytes(12));
$check = static function (bool $ok, string $message): void {
  if (!$ok) {
    throw new RuntimeException($message);
  }
};

try {
  for ($i = 1; $i <= 6; $i++) {
    $repo->record($a, new RecentWorkEvent('projects', 'project', $i, 'detail'));
  }
  $repo->record($b, new RecentWorkEvent('finance', 'invoice', 101, 'detail'));

  $rowsA = $repo->listForOperator($a);
  $rowsB = $repo->listForOperator($b);
  $check(count($rowsA) === 5, 'Operator A should have exactly five items.');
  $check(array_map(static fn(array $row): int => (int) $row['resource_id'], $rowsA) === [6, 5, 4, 3, 2],
    'Operator A ordering or five-item pruning failed.');
  $check(count($rowsB) === 1 && $rowsB[0]['module'] === 'finance'
    && (int) $rowsB[0]['resource_id'] === 101, 'Operator B data isolation failed.');
  $repo->record($a, new RecentWorkEvent('projects', 'project', 6, 'detail'));
  $check(count($repo->listForOperator($a)) === 5, 'Duplicate event should not increase item count.');
  echo "Recent-work write, five-item retention, deduplication and operator isolation: OK\n";
} finally {
  $repo->clearForOperator($a);
  $repo->clearForOperator($b);
  echo "Disposable fixture operators cleaned up.\n";
}
