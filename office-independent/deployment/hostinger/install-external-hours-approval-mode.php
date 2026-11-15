<?php
declare(strict_types=1);
/** Explicit CLI-only Office-test schema migration. Never run from web requests. */
if (PHP_SAPI !== 'cli') exit(3);
$mode=$argv[1] ?? '';
if (!in_array($mode,['--preflight','--inspect'],true)) { fwrite(STDERR,"MODE_REQUIRED\n"); exit(2); }
$configPath=getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($configPath) || !is_file($configPath)) { fwrite(STDERR,"CONFIG_MISSING\n"); exit(2); }
$config=require $configPath;
$dsn=$config['OFFICE_INTAKE_PDO_DSN'] ?? '';
if (($config['OFFICE_BASE_PATH'] ?? '') !== '/office-test'
    || !is_string($dsn)
    || !preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/',$dsn,$match)
    || !str_ends_with($match[1],'_office_test')) {
    fwrite(STDERR,"TEST_SCOPE_REJECTED\n"); exit(3);
}
$db=new PDO($dsn,(string)($config['OFFICE_INTAKE_DB_USER'] ?? ''),
    (string)($config['OFFICE_INTAKE_DB_PASSWORD'] ?? ''),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$table='office_inzet_external_hours_review_decisions';
$q=$db->prepare('SELECT COLUMN_NAME,IS_NULLABLE,COLUMN_TYPE,COLUMN_DEFAULT
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
$q->execute([$table]);
$cols=[];
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $cols[$row['COLUMN_NAME']]=$row;
foreach (['id','external_claim_id','first_reviewer','second_reviewer','approved_payable_seconds'] as $required) {
    if (!isset($cols[$required])) { fwrite(STDERR,"BASE_SCHEMA_MISSING\n"); exit(4); }
}
$hasMode=isset($cols['approval_mode']);
if ($hasMode) {
    if (strtolower((string)$cols['approval_mode']['COLUMN_TYPE'])!=="enum('four_eyes','highest_role_single')"
        || $cols['approval_mode']['IS_NULLABLE']!=='NO'
        || $cols['second_reviewer']['IS_NULLABLE']!=='YES') {
        fwrite(STDERR,"MIGRATED_SCHEMA_MISMATCH\n"); exit(5);
    }
} elseif ($cols['second_reviewer']['IS_NULLABLE']!=='NO') {
    fwrite(STDERR,"PARTIAL_MIGRATION_REQUIRES_MANUAL_REVIEW\n"); exit(6);
}
$q=$db->prepare('SELECT CONSTRAINT_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN (?,?)');
$q->execute(['chk_external_review_distinct','chk_external_review_mode']);
$checks=[];
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $checks[$row['CONSTRAINT_NAME']]=$row['CHECK_CLAUSE'];
if ($mode==='--inspect') {
    $reviewerNullable=$cols['second_reviewer']['IS_NULLABLE'] ?? 'UNKNOWN';
    $legacy=isset($checks['chk_external_review_distinct']);
    $modern=isset($checks['chk_external_review_mode']);
    echo "APPROVAL_MODE_COLUMN=".($hasMode?'PRESENT':'MISSING')."\n";
    echo "SECOND_REVIEWER_NULLABLE=".$reviewerNullable."\n";
    echo "LEGACY_DISTINCT_CHECK=".($legacy?'PRESENT':'MISSING')."\n";
    echo "MODE_CHECK=".($modern?'PRESENT':'MISSING')."\n";
    $stage='MANUAL_REVIEW_REQUIRED';
    if (!$hasMode && $reviewerNullable==='NO' && $legacy && !$modern) $stage='LEGACY';
    elseif ($hasMode && $reviewerNullable==='NO' && $legacy && !$modern) $stage='COLUMN_ADDED';
    elseif ($hasMode && $reviewerNullable==='YES' && $legacy && !$modern) $stage='REVIEWER_NULLABLE';
    elseif ($hasMode && $reviewerNullable==='YES' && !$legacy && !$modern) $stage='CHECKS_MISSING';
    elseif ($hasMode && $reviewerNullable==='YES' && !$legacy && $modern) $stage='COMPLETE';
    echo "MIGRATION_STAGE=".$stage."\n";
    echo "INSPECT_READ_ONLY_NO_CHANGES\n";
    exit($stage==='MANUAL_REVIEW_REQUIRED'?7:0);
}
if ($hasMode && !isset($checks['chk_external_review_mode'])) {
    fwrite(STDERR,"MODE_CHECK_MISSING\n"); exit(7);
}
if (!$hasMode && !isset($checks['chk_external_review_distinct'])) {
    fwrite(STDERR,"LEGACY_CHECK_MISSING\n"); exit(8);
}
echo "APPROVAL_MODE=".($hasMode?'PRESENT':'MISSING')."\n";
if ($mode==='--preflight') { echo "PREFLIGHT_NO_CHANGES\n"; exit(0); }
if ($hasMode) { echo "APPROVAL_MODE_ALREADY_INSTALLED_NO_CHANGES\n"; exit(0); }
require_once __DIR__.'/OfficeExternalHoursApprovalModeMigration.php';
foreach (\Brebo\Office\Inzet\OfficeExternalHoursApprovalModeMigration::statements() as $sql) $db->exec($sql);
echo "APPROVAL_MODE_MIGRATION_COMPLETE_NO_DECISIONS_INSERTED\n";
