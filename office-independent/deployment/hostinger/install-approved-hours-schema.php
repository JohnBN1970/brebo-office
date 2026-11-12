<?php
declare(strict_types=1);
/** CLI-only, additive Office-test migration; no employee hours are created. */
if (PHP_SAPI !== 'cli') { exit(3); }
$mode = $argv[1] ?? '';
if (!in_array($mode, ['--preflight','--apply'], true)) { fwrite(STDERR,"MODE_REQUIRED\n"); exit(2); }
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
$expected = [
    'office_inzet_approved_hours'=>['id','project_id','person_id','work_date','approved_seconds','source_kind','source_reference','source_sha256','approved_by','approved_at','approval_reference','recorded_at'],
    'office_onsite_hours_decisions'=>['id','decision_reference','in_event_id','out_event_id','project_id','person_id','work_date','status','reviewed_seconds','reviewer_id','decided_at','recorded_at'],
];
$existing = [];
foreach ($expected as $table=>$columns) {
    $stmt=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    $actual=$stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($actual !== [] && (array_diff($columns,$actual) !== [] || array_diff($actual,$columns) !== [])) {
        fwrite(STDERR,"SCHEMA_MISMATCH_".$table."\n"); exit(4);
    }
    $existing[$table] = $actual !== [];
}
$person=$db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_inzet_people' AND COLUMN_NAME='id'")->fetchColumn();
echo "PERSON_ID_COLUMN_TYPE=".($person === false ? 'MISSING' : strtolower((string)$person))."\n";
if ($person !== 'bigint unsigned') { fwrite(STDERR,"PERSON_ID_SCHEMA_UNSUPPORTED\n"); exit(5); }
foreach ($existing as $table=>$present) echo $table.'='.($present?'PRESENT':'MISSING')."\n";
if ($mode === '--preflight') { echo "PREFLIGHT_NO_CHANGES\n"; exit(0); }
require_once __DIR__.'/OfficeApprovedHoursSchema.php';
require_once __DIR__.'/OfficeApprovedHoursDecisionSchema.php';
foreach ([
    'office_onsite_hours_decisions'=>\Brebo\Office\Inzet\OfficeApprovedHoursDecisionSchema::statements(),
    'office_inzet_approved_hours'=>\Brebo\Office\Inzet\OfficeApprovedHoursSchema::statements(),
] as $table=>$statements) {
    if (!$existing[$table]) {
        foreach ($statements as $sql) $db->exec($sql);
        echo $table."=CREATED\n";
    }
}
echo "APPROVED_HOURS_SCHEMA_INSTALL_COMPLETE_NO_DATA_INSERTED\n";
