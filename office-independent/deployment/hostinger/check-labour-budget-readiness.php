<?php
declare(strict_types=1);
/** Read-only Office-test readiness: no approval, no posting, no DDL. */
if (PHP_SAPI !== 'cli') exit(3);
$configPath = getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($configPath) || !is_file($configPath)) { fwrite(STDERR,"CONFIG_MISSING\n"); exit(2); }
$config = require $configPath;
$dsn = $config['OFFICE_INTAKE_PDO_DSN'] ?? '';
if (($config['OFFICE_BASE_PATH'] ?? '') !== '/office-test'
    || !is_string($dsn)
    || !preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/', $dsn, $match)
    || !str_ends_with($match[1], '_office_test')) {
    fwrite(STDERR,"TEST_SCOPE_REJECTED\n"); exit(3);
}
$db = new PDO($dsn, (string)($config['OFFICE_INTAKE_DB_USER'] ?? ''),
    (string)($config['OFFICE_INTAKE_DB_PASSWORD'] ?? ''),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$tables = [
    'office_inzet_labour_budget_debits',
    'office_inzet_approved_hours',
    'office_inzet_shifts',
    'office_onsite_hours_decisions',
    'office_onsite_event_inbox',
];
foreach ($tables as $table) {
    $q = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$table]);
    if ((int)$q->fetchColumn() !== 1) { fwrite(STDERR,"REQUIRED_TABLE_MISSING_".$table."\n"); exit(4); }
    echo "TABLE_".$table."=PRESENT\n";
}
$q = $db->query("SELECT COUNT(*) FROM office_inzet_labour_budget_debits");
echo "EXISTING_BUDGET_DEBIT_ROWS=".(int)$q->fetchColumn()."\n";
$q = $db->query("SELECT COUNT(*) FROM office_inzet_approved_hours");
echo "EXISTING_APPROVED_HOURS_ROWS=".(int)$q->fetchColumn()."\n";
$q = $db->query("SELECT COUNT(*) FROM office_inzet_shifts WHERE status IN ('planned','confirmed')");
echo "EXISTING_ACTIVE_PLANNED_SHIFTS=".(int)$q->fetchColumn()."\n";
$q = $db->query("SELECT COUNT(*) FROM office_inzet_labour_budget_debits d
 LEFT JOIN office_inzet_approved_hours a ON a.id=d.approved_hours_id
 LEFT JOIN office_inzet_shifts s ON s.id=d.shift_id
 WHERE a.id IS NULL OR s.id IS NULL OR d.project_id<>a.project_id
 OR d.person_id<>a.person_id OR d.approved_seconds<>a.approved_seconds
 OR d.source_sha256<>a.source_sha256 OR d.budget_debit_seconds<>d.planned_seconds
 OR d.variance_seconds<>d.approved_seconds-d.planned_seconds");
$invalid=(int)$q->fetchColumn();
echo "INVALID_BUDGET_DEBITS=".$invalid."\n";
if ($invalid !== 0) exit(5);
echo "LABOUR_BUDGET_READONLY_PREFLIGHT_OK_NO_CHANGES\n";
