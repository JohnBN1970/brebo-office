<?php
declare(strict_types=1);
/** Office-test ONLY additive Gantt schema installer. No project data inserts. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(3); }
$apply = ($argv[1] ?? '') === '--apply';
if (!in_array($argv[1] ?? '', ['--dry-run','--apply'], true)) { fwrite(STDERR,"MODE_REQUIRED\n"); exit(2); }
$path = getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($path) || !is_file($path)) { fwrite(STDERR,"CONFIG_MISSING\n"); exit(2); }
$config = require $path;
$dsn = $config['OFFICE_INTAKE_PDO_DSN'] ?? '';
if (($config['OFFICE_BASE_PATH'] ?? '') !== '/office-test' || !is_string($dsn)
    || !preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/', $dsn, $match)
    || !str_ends_with($match[1], '_office_test')) {
    fwrite(STDERR,"TEST_SCOPE_REJECTED\n"); exit(3);
}
$db = new PDO($dsn, (string)($config['OFFICE_INTAKE_DB_USER'] ?? ''),
    (string)($config['OFFICE_INTAKE_DB_PASSWORD'] ?? ''),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
$stmt = $db->query("SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_projects' AND COLUMN_NAME='id'");
$projectId = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$projectId || !in_array(strtolower((string)$projectId['DATA_TYPE']), ['int','bigint'], true)) {
    fwrite(STDERR,"PROJECT_ID_SCHEMA_UNSUPPORTED\n"); exit(4);
}
$stmt = $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_project_plan_items'");
$exists = (int)$stmt->fetchColumn() > 0;
if ($exists) {
    $cols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_project_plan_items'")->fetchAll(PDO::FETCH_COLUMN);
    $required = ['id','project_id','title','planned_start','planned_end','status','critical_note','sort_order'];
    if (array_diff($required,$cols) !== []) { fwrite(STDERR,"EXISTING_SCHEMA_MISMATCH\n"); exit(5); }
    echo "GANTT_SCHEMA_ALREADY_PRESENT\n"; exit(0);
}
echo "GANTT_SCHEMA_MISSING\n";
if (!$apply) { echo "DRY_RUN_NO_CHANGES\n"; exit(0); }
$sqlPath = __DIR__.'/sql/office-project-gantt-plan.sql';
$sql = file_get_contents($sqlPath);
if (!is_string($sql) || trim($sql) === '') { fwrite(STDERR,"SQL_MISSING\n"); exit(6); }
$db->exec($sql);
echo "GANTT_SCHEMA_CREATED\n";
