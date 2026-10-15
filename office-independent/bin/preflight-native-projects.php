<?php

declare(strict_types=1);

// Read-only preflight. NEVER creates or changes tables or records.
// CLI usage: OFFICE_INTAKE_PDO_DSN=... OFFICE_INTAKE_DB_USER=... php office-independent/bin/preflight-native-projects.php
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
if (!is_string($dsn) || $dsn === '') {
  fwrite(STDERR, "Office DSN ontbreekt.\n");
  exit(2);
}
try {
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
  if ($driver !== 'mysql') throw new RuntimeException('Preflight vereist MySQL/MariaDB.');
  $identity = $db->query('SELECT DATABASE()')->fetchColumn();
  $expected = getenv('OFFICE_EXPECTED_TEST_DATABASE');
  if (!is_string($expected) || $expected === '' || !is_string($identity) || !hash_equals($expected, $identity)) {
    throw new RuntimeException('Database-identiteit niet bevestigd: alleen de expliciet ingestelde testdatabase is toegestaan.');
  }
  $required = [
    'office_projects' => ['id','code','title','status'],
    'office_buildings' => ['id','name'],
    'office_project_buildings' => ['project_id','building_id'],
    'office_project_memberships' => ['project_id','operator_id','permission'],
    'office_operator_accounts' => ['id','active'],
  ];
  $tables = [];
  foreach ($required as $table => $columns) {
    $statement = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table');
    $statement->execute([':schema' => $identity, ':table' => $table]);
    $present = $statement->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_values(array_diff($columns, $present));
    $tables[$table] = ['present' => $present !== [], 'missing_columns' => $missing];
  }
  $ready = !array_filter($tables, static fn(array $t): bool => !$t['present'] || $t['missing_columns'] !== []);
  $counts = [];
  if ($ready) {
    foreach (['office_projects','office_buildings','office_project_buildings','office_project_memberships'] as $table) {
      $counts[$table] = (int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
    $counts['orphan_project_buildings'] = (int) $db->query(
      'SELECT COUNT(*) FROM office_project_buildings pb LEFT JOIN office_projects p ON p.id=pb.project_id LEFT JOIN office_buildings b ON b.id=pb.building_id WHERE p.id IS NULL OR b.id IS NULL'
    )->fetchColumn();
    $counts['orphan_memberships'] = (int) $db->query(
      'SELECT COUNT(*) FROM office_project_memberships m LEFT JOIN office_projects p ON p.id=m.project_id LEFT JOIN office_operator_accounts a ON a.id=m.operator_id WHERE p.id IS NULL OR a.id IS NULL'
    )->fetchColumn();
    $ready = $counts['orphan_project_buildings'] === 0 && $counts['orphan_memberships'] === 0;
  }
  echo json_encode(['database' => $identity, 'read_only' => true, 'schema_ready' => $ready, 'tables' => $tables, 'counts' => $counts], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
  exit($ready ? 0 : 1);
} catch (Throwable $e) {
  fwrite(STDERR, "Native Office preflight niet geslaagd: " . $e->getMessage() . "\n");
  exit(2);
}
