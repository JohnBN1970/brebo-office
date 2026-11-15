<?php
declare(strict_types=1);
/** CLI-only additive Office-test schema migration; no hour or budget records are written. */
if (PHP_SAPI !== 'cli') exit(3);
$mode = $argv[1] ?? '';
if (!in_array($mode, ['--preflight', '--apply'], true)) { fwrite(STDERR, "MODE_REQUIRED\n"); exit(2); }
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
$types = [
    'office_inzet_approved_hours' => 'id',
    'office_inzet_shifts' => 'id',
];
foreach ($types as $table => $column) {
    $q = $db->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$table, $column]);
    $type = $q->fetchColumn();
    if (!is_string($type) || !preg_match('/^bigint(?:\\(\\d+\\))? unsigned$/i', trim($type))) {
        fwrite(STDERR, "PARENT_SCHEMA_UNSUPPORTED_".$table."\n"); exit(4);
    }
}
$table = 'office_inzet_labour_budget_debits';
$columns = ['id','approved_hours_id','shift_id','project_id','person_id','work_date',
    'approved_seconds','planned_seconds','budget_debit_seconds','variance_seconds','source_sha256','recorded_at'];
$q = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
$q->execute([$table]);
$actual = $q->fetchAll(PDO::FETCH_COLUMN);
if ($actual !== [] && (array_diff($columns,$actual) !== [] || array_diff($actual,$columns) !== [])) {
    fwrite(STDERR, "SCHEMA_MISMATCH\n"); exit(5);
}
if ($actual !== []) {
    $q = $db->prepare('SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$table]);
    $indexes = $q->fetchAll(PDO::FETCH_ASSOC);
    foreach (['uq_labour_budget_approval'=>'approved_hours_id','uq_labour_budget_shift'=>'shift_id'] as $name=>$column) {
        $matches = array_values(array_filter($indexes, static fn(array $row):bool =>
            $row['INDEX_NAME']===$name && $row['COLUMN_NAME']===$column && (int)$row['NON_UNIQUE']===0));
        if (count($matches)!==1) { fwrite(STDERR, "UNIQUE_INDEX_MISMATCH_".$name."\n"); exit(6); }
    }
}
echo $table.'='.($actual===[]?'MISSING':'PRESENT')."\n";
if ($mode==='--preflight') { echo "PREFLIGHT_NO_CHANGES\n"; exit(0); }
require_once __DIR__.'/OfficeLabourBudgetDebitSchema.php';
foreach (\Brebo\Office\Inzet\OfficeLabourBudgetDebitSchema::statements() as $sql) $db->exec($sql);
echo "LABOUR_BUDGET_SCHEMA_INSTALL_COMPLETE_NO_DATA_INSERTED\n";
