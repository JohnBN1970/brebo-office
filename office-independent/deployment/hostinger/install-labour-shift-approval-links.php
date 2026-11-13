<?php
declare(strict_types=1);
/** CLI-only Office-test additive migration. Never writes approval or budget data. */
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
foreach (['office_inzet_approved_hours','office_inzet_shifts'] as $parent) {
    $q=$db->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$parent,'id']);
    $type=$q->fetchColumn();
    if (!is_string($type) || !preg_match('/^bigint(?:\\(\\d+\\))? unsigned$/i',trim($type))) {
        fwrite(STDERR,"PARENT_SCHEMA_UNSUPPORTED_".$parent."\n"); exit(4);
    }
}
$table='office_inzet_labour_shift_approval_links';
$expected=['id','shift_id','approved_hours_id','approved_seconds','source_sha256','recorded_at'];
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
        $r['INDEX_NAME']==='uq_labour_shift_approval'
        && $r['COLUMN_NAME']==='approved_hours_id'
        && (int)$r['NON_UNIQUE']===0));
    if (count($unique)!==1) { fwrite(STDERR,"UNIQUE_INDEX_MISMATCH\n"); exit(6); }
    $q=$db->prepare('SELECT CONSTRAINT_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL');
    $q->execute([$table]);
    $fks=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach (['shift_id'=>'office_inzet_shifts','approved_hours_id'=>'office_inzet_approved_hours'] as $col=>$parent) {
        $found=array_filter($fks,static fn(array $r):bool=>
            $r['COLUMN_NAME']===$col && $r['REFERENCED_TABLE_NAME']===$parent
            && $r['REFERENCED_COLUMN_NAME']==='id');
        if (count($found)!==1) { fwrite(STDERR,"FOREIGN_KEY_MISMATCH_".$col."\n"); exit(7); }
    }
}
echo $table.'='.($actual===[]?'MISSING':'PRESENT')."\n";
if ($mode==='--preflight') { echo "PREFLIGHT_NO_CHANGES\n"; exit(0); }
require_once __DIR__.'/OfficeLabourShiftApprovalLinkSchema.php';
foreach (\Brebo\Office\Inzet\OfficeLabourShiftApprovalLinkSchema::statements() as $sql) $db->exec($sql);
echo "LABOUR_SHIFT_LINK_SCHEMA_INSTALL_COMPLETE_NO_DATA_INSERTED\n";
