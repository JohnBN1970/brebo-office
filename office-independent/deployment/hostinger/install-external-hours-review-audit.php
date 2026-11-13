<?php
declare(strict_types=1);
/** CLI-only Office-test review audit migration; no decisions inserted. */
if (PHP_SAPI !== 'cli') exit(3);
$mode=$argv[1] ?? '';
if (!in_array($mode,['--preflight','--apply'],true)) { fwrite(STDERR,"MODE_REQUIRED\n"); exit(2); }
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
$q=$db->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
$q->execute(['office_inzet_external_hours_claims','id']);
$type=$q->fetchColumn();
if (!is_string($type) || !preg_match('/^bigint(?:\\(\\d+\\))? unsigned$/i',trim($type))) {
    fwrite(STDERR,"PARENT_SCHEMA_UNSUPPORTED\n"); exit(4);
}
$table='office_inzet_external_hours_review_decisions';
$expected=['id','external_claim_id','decision_reference','source_line_sha256','present_seconds',
    'invoiced_seconds','approved_payable_seconds','first_reviewer','second_reviewer','reason','decided_at'];
$q=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
$q->execute([$table]);
$actual=$q->fetchAll(PDO::FETCH_COLUMN);
if ($actual!==[] && (array_diff($expected,$actual)!==[] || array_diff($actual,$expected)!==[])) {
    fwrite(STDERR,"SCHEMA_MISMATCH\n"); exit(5);
}
if ($actual!==[]) {
    $q=$db->prepare('SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$table]);
    $indexes=$q->fetchAll(PDO::FETCH_ASSOC);
    $unique=array_values(array_filter($indexes,static fn(array $r):bool=>
        $r['INDEX_NAME']==='uq_external_review_decision_reference'
        && $r['COLUMN_NAME']==='decision_reference' && (int)$r['NON_UNIQUE']===0));
    if (count($unique)!==1) { fwrite(STDERR,"UNIQUE_INDEX_MISMATCH\n"); exit(6); }
    $q=$db->prepare('SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL');
    $q->execute([$table]);
    $fks=$q->fetchAll(PDO::FETCH_ASSOC);
    $matches=array_filter($fks,static fn(array $r):bool=>
        $r['COLUMN_NAME']==='external_claim_id'
        && $r['REFERENCED_TABLE_NAME']==='office_inzet_external_hours_claims'
        && $r['REFERENCED_COLUMN_NAME']==='id');
    if (count($matches)!==1) { fwrite(STDERR,"FOREIGN_KEY_MISMATCH\n"); exit(7); }
}
echo $table.'='.($actual===[]?'MISSING':'PRESENT')."\n";
if ($mode==='--preflight') { echo "PREFLIGHT_NO_CHANGES\n"; exit(0); }
require_once __DIR__.'/OfficeExternalHoursReviewAuditSchema.php';
foreach (\Brebo\Office\Inzet\OfficeExternalHoursReviewAuditSchema::statements() as $sql) $db->exec($sql);
echo "EXTERNAL_REVIEW_AUDIT_INSTALL_COMPLETE_NO_DECISIONS_INSERTED\n";
