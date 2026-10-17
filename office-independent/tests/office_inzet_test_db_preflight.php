<?php

declare(strict_types=1);

/**
 * READ-ONLY Inzet schema preflight for the isolated Office test database.
 * Usage: OFFICE_INTAKE_PDO_DSN=... OFFICE_INTAKE_DB_USER=... OFFICE_INTAKE_DB_PASSWORD=... php tests/office_inzet_test_db_preflight.php
 * Never creates tables, writes rows, or prints credentials.
 */
if (getenv('OFFICE_INZET_PREFLIGHT_TARGET') !== 'office-test') {
  fwrite(STDERR, "Refusing: set OFFICE_INZET_PREFLIGHT_TARGET=office-test.\n");
  exit(2);
}
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) {
  fwrite(STDERR, "Refusing: MySQL DSN required.\n");
  exit(2);
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_EMULATE_PREPARES => false,
]);
$name = (string)$db->query('SELECT DATABASE()')->fetchColumn();
if ($name !== 'u213420663_office_test') {
  fwrite(STDERR, "Refusing: not the approved Office test database.\n");
  exit(2);
}
$tables = ['office_projects', 'office_inzet_people', 'office_inzet_project_team',
  'office_inzet_availability', 'office_inzet_shifts', 'office_inzet_clock_events'];
$stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
foreach ($tables as $table) {
  $stmt->execute([$table]);
  echo $table . ': ' . ((int)$stmt->fetchColumn() === 1 ? 'present' : 'missing') . PHP_EOL;
}
$cols = $db->query("SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.columns
 WHERE table_schema = DATABASE() AND table_name = 'office_projects' AND column_name = 'id'");
$column = $cols->fetch(PDO::FETCH_ASSOC);
echo 'office_projects.id: ' . ($column ? $column['COLUMN_TYPE'] : 'not found') . PHP_EOL;
echo "Read-only preflight completed; no schema changes made.\n";
