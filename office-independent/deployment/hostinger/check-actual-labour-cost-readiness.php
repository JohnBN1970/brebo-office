<?php
declare(strict_types=1);
/** CLI-only Office-test read-only acceptance: no schema changes, rate edits or postings. */
if (PHP_SAPI !== 'cli') exit(3);
$path=getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($path)||!is_file($path)) {fwrite(STDERR,"CONFIG_MISSING\n");exit(2);}
$config=require $path;
$dsn=$config['OFFICE_INTAKE_PDO_DSN']??'';
if (($config['OFFICE_BASE_PATH']??'')!=='/office-test'||!is_string($dsn)
    ||!preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/',$dsn,$m)
    ||!str_ends_with($m[1],'_office_test')) {fwrite(STDERR,"TEST_SCOPE_REJECTED\n");exit(3);}
$db=new PDO($dsn,(string)($config['OFFICE_INTAKE_DB_USER']??''),
    (string)($config['OFFICE_INTAKE_DB_PASSWORD']??''),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
foreach (['office_inzet_approved_hours','office_inzet_actual_labour_rates','office_inzet_actual_labour_costs'] as $table) {
    $q=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$table]);
    if ((int)$q->fetchColumn()!==1) {fwrite(STDERR,"MISSING_TABLE_".$table."\n");exit(4);}
    echo "TABLE_".$table."=PRESENT\n";
}
$checks=[
    'APPROVED_HOURS_COUNT'=>'SELECT COUNT(*) FROM office_inzet_approved_hours',
    'AUTHORIZED_ACTIVE_RATE_COUNT'=>"SELECT COUNT(*) FROM office_inzet_actual_labour_rates WHERE revoked_at IS NULL AND authorized_by <> ''",
    'POSTED_ACTUAL_COST_COUNT'=>'SELECT COUNT(*) FROM office_inzet_actual_labour_costs',
    'UNPOSTED_APPROVED_HOURS'=>'SELECT COUNT(*) FROM office_inzet_approved_hours a LEFT JOIN office_inzet_actual_labour_costs c ON c.approved_hours_id=a.id WHERE c.id IS NULL',
    'INVALID_COST_LINKS'=>'SELECT COUNT(*) FROM office_inzet_actual_labour_costs c LEFT JOIN office_inzet_approved_hours a ON a.id=c.approved_hours_id WHERE a.id IS NULL OR c.project_id<>a.project_id OR c.person_id<>a.person_id OR c.work_date<>a.work_date OR c.approved_seconds<>a.approved_seconds OR c.source_sha256<>a.source_sha256',
    'DUPLICATE_COST_SOURCES'=>'SELECT COUNT(*) FROM (SELECT approved_hours_id FROM office_inzet_actual_labour_costs GROUP BY approved_hours_id HAVING COUNT(*)>1) x',
];
$values=[];
foreach ($checks as $name=>$sql) {
    $values[$name]=(int)$db->query($sql)->fetchColumn();
    echo $name.'='.$values[$name]."\n";
}
if ($values['INVALID_COST_LINKS']!==0||$values['DUPLICATE_COST_SOURCES']!==0) {
    fwrite(STDERR,"LABOUR_COST_RECONCILIATION_FAILED\n");exit(5);
}
if ($values['UNPOSTED_APPROVED_HOURS']!==0) {
    echo "LABOUR_COST_COVERAGE=INCOMPLETE\n";
} else {
    echo "LABOUR_COST_COVERAGE=COMPLETE_FOR_CURRENT_LEDGER\n";
}
echo "LABOUR_COST_READONLY_ACCEPTANCE_OK_NO_CHANGES\n";
