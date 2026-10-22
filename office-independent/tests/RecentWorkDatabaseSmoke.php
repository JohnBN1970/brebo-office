<?php

declare(strict_types=1);

/**
 * Explicit, opt-in integration smoke for an isolated disposable TEST database.
 * Does not create tables, migrate schemas, or commit any data.
 *
 * Run only after independent database verification:
 * OFFICE_RECENT_WORK_TEST_DSN='mysql:host=...;dbname=..._test' \
 * OFFICE_RECENT_WORK_TEST_USER=... OFFICE_RECENT_WORK_TEST_PASSWORD=... \
 * OFFICE_RECENT_WORK_TEST_ACK=isolated-test php RecentWorkDatabaseSmoke.php
 */
if (getenv('OFFICE_RECENT_WORK_TEST_ACK') !== 'isolated-test') {
  fwrite(STDERR, "Refusing database smoke: explicit isolated-test acknowledgement required.\n");
  exit(2);
}
$dsn = getenv('OFFICE_RECENT_WORK_TEST_DSN');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) {
  fwrite(STDERR, "Refusing database smoke: separate MySQL test DSN required.\n");
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
if ($dbName === '' || !preg_match('/(?:^|_)test(?:_|$)|(?:^|_)staging(?:_|$)/i', $dbName)) {
  fwrite(STDERR, "Refusing database smoke: schema name must explicitly contain test or staging.\n");
  exit(2);
}
$table = $db->query("SHOW TABLES LIKE 'office_user_recent_work'")->fetchColumn();
if ($table === false) {
  fwrite(STDERR, "Test schema not migrated: office_user_recent_work is missing.\n");
  exit(2);
}
// Verify the exact contract, not merely that a similarly named table exists.
$columns = [];
foreach ($db->query('SHOW COLUMNS FROM office_user_recent_work')->fetchAll(PDO::FETCH_ASSOC) as $column) {
  $columns[$column['Field']] = $column;
}
foreach (['id', 'operator_id', 'module', 'resource_type', 'resource_id', 'view_key', 'position_json', 'updated_at'] as $required) {
  if (!isset($columns[$required])) {
    throw new RuntimeException('Missing recent-work column: ' . $required);
  }
}
$positionType = strtolower((string) $columns['position_json']['Type']);
if ($positionType !== 'json') {
  // MariaDB represents JSON as LONGTEXT with a JSON_VALID check constraint.
  // A plain LONGTEXT column must never pass this schema check.
  if ($positionType !== 'longtext') {
    throw new RuntimeException('Recent-work position_json must enforce JSON validity.');
  }
  $check = $db->prepare(
    'SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS '
    . 'WHERE CONSTRAINT_SCHEMA = :schema AND CONSTRAINT_NAME = :constraint'
  );
  $check->execute(['schema' => $dbName, 'constraint' => 'position_json']);
  $clause = (string) ($check->fetchColumn() ?: '');
  if (!preg_match('/json_valid\\s*\\(\\s*[`"]?position_json[`"]?\\s*\\)/i', $clause)) {
    throw new RuntimeException('Recent-work position_json requires a JSON_VALID check constraint.');
  }
}
$indexes = [];
foreach ($db->query('SHOW INDEX FROM office_user_recent_work')->fetchAll(PDO::FETCH_ASSOC) as $index) {
  $indexes[$index['Key_name']][(int) $index['Seq_in_index']] = $index['Column_name'];
}
$hasUnique = false;
foreach ($indexes as $name => $parts) {
  ksort($parts);
  if (array_values($parts) === ['operator_id', 'module', 'resource_type', 'resource_id', 'view_key']) {
    $hasUnique = true;
  }
}
if (!$hasUnique) {
  throw new RuntimeException('Recent-work deduplication index missing.');
}
$operator = 'smoke-' . bin2hex(random_bytes(12));
$other = 'smoke-' . bin2hex(random_bytes(12));
$repo = new Brebo\Office\RecentWork\PdoRecentWorkRepository($db);

// Read-only isolation check. No INSERT, DELETE, or migration against any DB.
// Transactional record()/clearForOperator() tests require a separately
// disposable database fixture because repository methods own transactions.
if ($repo->listForOperator($operator) !== [] || $repo->listForOperator($other) !== []) {
  throw new RuntimeException('Unexpected test-operator data.');
}
echo "Recent-work database connectivity, schema and empty test-operator reads: OK\n";
echo "No database changes performed.\n";
