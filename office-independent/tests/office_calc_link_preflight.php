<?php

declare(strict_types=1);

// Read-only preflight for the isolated Office TEST database.
// Run with the same test-only OFFICE_INTAKE_PDO_* environment as /office-test.
if (getenv('OFFICE_CALC_LAUNCH_ENABLED') === 'enabled') {
  fwrite(STDERR, "Refusing preflight with Calc launch enabled.\n");
  exit(2);
}
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
if (!is_string($dsn) || $dsn === '') {
  fwrite(STDERR, "Missing test database DSN.\n");
  exit(2);
}
// The preflight is intentionally restricted to the isolated test database.
if (getenv('OFFICE_NATIVE_PROJECTS_ENABLED') !== 'enabled'
  || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled') {
  fwrite(STDERR, "Office test project flags are not enabled.\n");
  exit(2);
}
if (!preg_match('/(?:^|;)dbname=([^;]+)/i', $dsn, $matches)
  || $matches[1] !== 'u213420663_office_test') {
  fwrite(STDERR, "Refusing non-test database.\n");
  exit(2);
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_EMULATE_PREPARES => false,
]);
$tables = [
  'office_project_buildings', 'office_buildings',
  'office_housing_types', 'office_project_housing_types',
  'office_project_calculation_scopes', 'office_calc_calculations',
  'office_calc_project_access', 'office_operator_accounts',
];
$stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :name');
$missing = [];
foreach ($tables as $table) {
  $stmt->execute([':name' => $table]);
  if ((int) $stmt->fetchColumn() !== 1) $missing[] = $table;
}
if ($missing !== []) {
  fwrite(STDERR, "Missing test tables: " . implode(', ', $missing) . "\n");
  exit(1);
}
$projectId = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($projectId)) {
  fwrite(STDERR, "Usage: php office_calc_link_preflight.php PROJECT_ID\n");
  exit(2);
}
require_once dirname(__DIR__) . '/src/Project/OfficeCalcBuildingTruthReader.php';
$truth = (new \Brebo\Office\Project\OfficeCalcBuildingTruthReader($db))->forProject($projectId);
foreach ($truth['buildings'] as $building) {
  foreach ($building['housing_types'] as $type) {
    echo 'Building ' . $building['id'] . ', type ' . $type['code']
      . ': ' . $type['dwelling_count'] . " dwellings\n";
  }
}
echo "Read-only Calc building-truth preflight OK\n";
