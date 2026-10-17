<?php

declare(strict_types=1);

/**
 * Explicit, test-only Inzet schema installer.
 * Requires deliberate operator confirmation. Never seeds personnel or events.
 * php tests/office_inzet_test_schema_install.php
 */
if (PHP_SAPI !== 'cli'
  || getenv('OFFICE_INZET_PREFLIGHT_TARGET') !== 'office-test'
  || getenv('OFFICE_INZET_SCHEMA_INSTALL_CONFIRM') !== 'INSTALL_INZET_ON_OFFICE_TEST') {
  fwrite(STDERR, "Refusing: explicit Office test install confirmation required.\n");
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
if ((string)$db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
  fwrite(STDERR, "Refusing: target database is not the approved Office test database.\n");
  exit(2);
}
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetSchema.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteClockEventSchema.php';

$projectId = $db->query("SELECT COLUMN_TYPE FROM information_schema.columns
 WHERE table_schema = DATABASE() AND table_name = 'office_projects' AND column_name = 'id'")->fetchColumn();
if (!is_string($projectId) || strtolower($projectId) !== 'bigint unsigned') {
  fwrite(STDERR, "Refusing: office_projects.id is not verified BIGINT UNSIGNED.\n");
  exit(2);
}
$statements = array_merge(
  \Brebo\Office\Inzet\OfficeInzetSchema::statements(),
  [\Brebo\Office\Inzet\OnSiteClockEventSchema::statement()]
);
$names = ['office_inzet_people','office_inzet_project_team','office_inzet_availability',
  'office_inzet_shifts','office_inzet_clock_events'];
$check = $db->prepare("SELECT COUNT(*) FROM information_schema.tables
 WHERE table_schema = DATABASE() AND table_name = ?");
foreach ($names as $name) {
  $check->execute([$name]);
  if ((int)$check->fetchColumn() !== 0) {
    fwrite(STDERR, "Refusing: Inzet table already exists; review its schema before migration.\n");
    exit(2);
  }
}
foreach ($statements as $index => $sql) {
  $db->exec($sql);
  echo "Created: " . $names[$index] . PHP_EOL;
}
echo "Office-test Inzet schema installed; no personnel or events created.\n";
