<?php
declare(strict_types=1);
/** Office-test only, CLI-only. Preflight is read-only; apply adds empty tables only. */
if (PHP_SAPI !== 'cli') exit(3);
$mode=$argv[1]??'';
if (!in_array($mode,['--preflight','--apply'],true)) {fwrite(STDERR,"MODE_REQUIRED\n");exit(2);}
$configPath=getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($configPath)||!is_file($configPath)) {fwrite(STDERR,"CONFIG_MISSING\n");exit(2);}
$config=require $configPath;
$dsn=$config['OFFICE_INTAKE_PDO_DSN']??'';
if (($config['OFFICE_BASE_PATH']??'')!=='/office-test'||!is_string($dsn)
    ||!preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/',$dsn,$m)
    ||!str_ends_with($m[1],'_office_test')) {fwrite(STDERR,"TEST_SCOPE_REJECTED\n");exit(3);}
$db=new PDO($dsn,(string)($config['OFFICE_INTAKE_DB_USER']??''),
    (string)($config['OFFICE_INTAKE_DB_PASSWORD']??''),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$expected=[
 'office_inzet_actual_labour_rates'=>['id','project_id','person_id','valid_from','valid_until','hourly_rate_cents','authorized_by','authorized_at','revoked_at'],
 'office_inzet_actual_labour_costs'=>['id','approved_hours_id','project_id','person_id','work_date','approved_seconds','hourly_rate_cents','amount_cents','source_sha256','recorded_at'],
];
foreach (['office_inzet_approved_hours','office_inzet_people'] as $required) {
    $q=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$required]);
    if ((int)$q->fetchColumn()!==1) {fwrite(STDERR,"DEPENDENCY_MISSING_".$required."\n");exit(4);}
}
$personType=$db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_inzet_people' AND COLUMN_NAME='id'")->fetchColumn();
if (!is_string($personType)||!preg_match('/^bigint(?:\\(\\d+\\))? unsigned$/i',trim($personType))) {
    fwrite(STDERR,"PERSON_ID_TYPE_UNSUPPORTED\n");exit(4);
}
$present=[];
foreach ($expected as $table=>$columns) {
    $q=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$table]);
    $actual=$q->fetchAll(PDO::FETCH_COLUMN);
    if ($actual!==[] && (array_diff($columns,$actual)!==[]||array_diff($actual,$columns)!==[])) {
        fwrite(STDERR,"SCHEMA_MISMATCH_".$table."\n");exit(5);
    }
    $present[$table]=$actual!==[];
    echo $table.'='.($present[$table]?'PRESENT':'MISSING')."\n";
}
if ($mode==='--preflight') {echo "LABOUR_COST_PREFLIGHT_NO_CHANGES\n";exit(0);}
require_once dirname(__DIR__,2).'/src/Inzet/OfficeApprovedLabourCostSchema.php';
foreach (\Brebo\Office\Inzet\OfficeApprovedLabourCostSchema::statements() as $sql) $db->exec($sql);
echo "LABOUR_COST_SCHEMA_APPLIED_NO_DATA_INSERTED\n";
