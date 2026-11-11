<?php
declare(strict_types=1);
/**
 * Read-only preflight for a REAL native Gantt test project.
 * Run on office-test with OFFICE_TEST_CONFIG_PATH set. No inserts or updates.
 */
$configPath = getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($configPath) || !is_file($configPath)) { fwrite(STDERR, "CONFIG_MISSING\n"); exit(2); }
$config = require $configPath;
$dsn = $config['OFFICE_INTAKE_PDO_DSN'] ?? '';
if (($config['OFFICE_BASE_PATH'] ?? '') !== '/office-test'
    || !is_string($dsn)
    || !preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/', $dsn, $match)
    || !str_ends_with($match[1], '_office_test')) {
    fwrite(STDERR, "TEST_SCOPE_REJECTED\n"); exit(3);
}
$db = new PDO($dsn, (string)($config['OFFICE_INTAKE_DB_USER'] ?? ''),
    (string)($config['OFFICE_INTAKE_DB_PASSWORD'] ?? ''),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
$stmt = $db->prepare('SELECT TABLE_NAME,COLUMN_NAME,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME,ORDINAL_POSITION');
$stmt->execute([$match[1]]);
$schema = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $schema[$row['TABLE_NAME']][] = $row;
foreach (['office_projects','office_project_memberships','office_project_buildings','office_project_milestones'] as $table) {
    echo $table.': '.(isset($schema[$table]) ? 'PRESENT' : 'MISSING')."\n";
    foreach ($schema[$table] ?? [] as $col) {
        echo '  '.$col['COLUMN_NAME'].' | nullable='.$col['IS_NULLABLE'].' | default='.($col['COLUMN_DEFAULT'] ?? 'NULL').' | '.$col['EXTRA']."\n";
    }
}
if (!isset($schema['office_projects'])) exit(4);
$stmt = $db->query("SELECT COUNT(*) FROM office_projects WHERE UPPER(code) LIKE 'DEMO-%' OR UPPER(code) LIKE 'TEST-%'");
echo 'TEST_OR_DEMO_PROJECT_COUNT='.(int)$stmt->fetchColumn()."\n";
$stmt = $db->prepare("SELECT p.id,p.code,p.title,p.status,COUNT(m.operator_id) AS membership_count FROM office_projects p LEFT JOIN office_project_memberships m ON m.project_id=p.id WHERE p.code=? GROUP BY p.id,p.code,p.title,p.status");
$stmt->execute(['TEST-2026-001']);
$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
echo 'TARGET_TEST_PROJECT_COUNT='.count($rows)."\n";
foreach ($rows as $row) echo 'TARGET_TEST_PROJECT_ID='.(int)$row['id'].'; MEMBERSHIPS='.(int)$row['membership_count']."\n";
echo "READ_ONLY_GANTT_PREFLIGHT_COMPLETE\n";
