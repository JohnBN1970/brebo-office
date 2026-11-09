<?php
declare(strict_types=1);

/**
 * Read-only installation preflight for personal dashboards.
 * Run on the isolated Office-test host. Does not mutate database/config.
 */
$configPath = $argv[1] ?? '';
if (!is_string($configPath) || !is_file($configPath)) {
  fwrite(STDERR, "FAIL CONFIG_MISSING\n"); exit(2);
}
$config = require $configPath;
if (!is_array($config) || ($config['OFFICE_BASE_PATH'] ?? '') !== '/office-test') {
  fwrite(STDERR, "FAIL TEST_SCOPE\n"); exit(2);
}
$dsn = $config['OFFICE_INTAKE_PDO_DSN'] ?? '';
if (!is_string($dsn) || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $dsn)) {
  fwrite(STDERR, "FAIL ISOLATED_DSN\n"); exit(2);
}
try {
  $db = new PDO($dsn, $config['OFFICE_INTAKE_DB_USER'] ?? '', $config['OFFICE_INTAKE_DB_PASSWORD'] ?? '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $name = $db->query("SELECT DATABASE()")->fetchColumn();
  if ($name !== 'u213420663_office_test') throw new RuntimeException('Wrong database');
  $tables = ['office_operator_accounts','office_projects','office_project_memberships','office_dashboard_preferences'];
  $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
  foreach ($tables as $table) {
    $stmt->execute([':table' => $table]);
    echo ((int)$stmt->fetchColumn() === 1 ? 'PASS ' : 'MISSING ') . $table . "\n";
  }
  $columns = $db->query("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_operator_accounts' AND COLUMN_NAME='id'")->fetchAll(PDO::FETCH_ASSOC);
  if (count($columns) !== 1) throw new RuntimeException('Operator key missing');
  echo 'OPERATOR_KEY_TYPE=' . $columns[0]['COLUMN_TYPE'] . "\n";
  $pref = $db->query("SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_dashboard_preferences'")->fetchAll(PDO::FETCH_ASSOC);
  if ($pref) {
    $cols = array_column($pref,'COLUMN_TYPE','COLUMN_NAME');
    if (!isset($cols['operator_id'],$cols['layout_json'],$cols['updated_at'])) throw new RuntimeException('Preference schema incomplete');
    if ($cols['operator_id'] !== $columns[0]['COLUMN_TYPE']) throw new RuntimeException('Operator key type mismatch');
    echo "PASS PREFERENCES_SCHEMA\n";
  } else {
    echo "PENDING MIGRATION\n";
  }
  $query = $db->prepare("SELECT COUNT(DISTINCT p.id) FROM office_projects p JOIN office_project_memberships m ON m.project_id=p.id JOIN office_operator_accounts a ON a.id=m.operator_id WHERE m.operator_id=:id AND m.permission IN ('read','manage') AND a.active=1");
  $query->execute([':id'=>0]);
  echo "PASS PROJECT_ACL_QUERY\n";
} catch (Throwable $e) {
  fwrite(STDERR, "FAIL PREFLIGHT " . $e->getMessage() . "\n"); exit(3);
}
