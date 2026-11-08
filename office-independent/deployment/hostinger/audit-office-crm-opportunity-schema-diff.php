<?php
declare(strict_types=1);
/** Read-only structural compatibility audit for existing Office-test opportunity tables. */
if (PHP_SAPI!=='cli' || getenv('OFFICE_BASE_PATH')!=='/office-test') { fwrite(STDERR,"ABORT: CLI Office-test only\n"); exit(2); }
$root='/home/u213420663/domains/brebobv.nl/office_calc_test';
$path=getenv('OFFICE_TEST_CONFIG_PATH');
if ($path!==$root.'/config/config.php' || !is_file($path)) { fwrite(STDERR,"ABORT: isolated config required\n"); exit(2); }
$config=require $path;
if (!is_array($config) || ($config['OFFICE_BASE_PATH']??null)!=='/office-test') { fwrite(STDERR,"ABORT: wrong environment\n"); exit(2); }
$dsn=$config['OFFICE_INTAKE_PDO_DSN']??null;
$user=$config['OFFICE_INTAKE_DB_USER']??null;
$password=$config['OFFICE_INTAKE_DB_PASSWORD']??null;
if (!is_string($dsn) || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn) || $user!=='u213420663_office_test' || !is_string($password) || $password==='') { fwrite(STDERR,"ABORT: wrong database scope\n"); exit(2); }
try {
    $db=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($db->query('SELECT DATABASE()')->fetchColumn()!=='u213420663_office_test') throw new RuntimeException('wrong database');
    $required=[
      'office_crm_opportunities'=>[
        'id'=>'bigint unsigned','relation_id'=>'bigint unsigned','contact_id'=>'bigint unsigned',
        'title'=>'varchar(255)','stage'=>'varchar(32)','description'=>'text',
        'expected_value'=>'decimal(14,2)','expected_close_date'=>'date',
        'next_action'=>'varchar(255)','next_action_at'=>'datetime',
        'opname_required'=>'tinyint','status'=>'varchar(16)',
        'created_at'=>'timestamp','updated_at'=>'timestamp'],
      'office_crm_opportunity_activities'=>[
        'id'=>'bigint unsigned','opportunity_id'=>'bigint unsigned',
        'activity_type'=>'varchar(32)','title'=>'varchar(255)',
        'scheduled_at'=>'datetime','completed_at'=>'datetime','created_at'=>'timestamp']
    ];
    $query=$db->prepare("SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,COLUMN_KEY,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('office_crm_opportunities','office_crm_opportunity_activities') ORDER BY TABLE_NAME,ORDINAL_POSITION");
    $query->execute(['u213420663_office_test']);
    $found=[];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) $found[$row['TABLE_NAME']][$row['COLUMN_NAME']]=$row;
    $eng=$db->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('office_crm_opportunities','office_crm_opportunity_activities')");
    $eng->execute(['u213420663_office_test']); $tables=$eng->fetchAll(PDO::FETCH_KEY_PAIR);
    $issues=0;
    foreach ($required as $table=>$columns) {
        echo "TABLE ".$table." ".(isset($tables[$table])?'PRESENT':'ABSENT')."\n";
        if (!isset($tables[$table])) continue;
        if ($tables[$table]!=='InnoDB') { echo "  ENGINE_MISMATCH\n"; $issues++; }
        foreach ($columns as $name=>$expected) {
            $col=$found[$table][$name]??null;
            if ($col===null) { echo "  MISSING ".$name."\n"; $issues++; continue; }
            $type=strtolower($col['COLUMN_TYPE']);
            $ok=$expected==='bigint unsigned'?(bool)preg_match('/^bigint(?:\\(20\\))? unsigned$/',$type):($expected==='tinyint'?str_starts_with($type,'tinyint'):($type===$expected));
            if (!$ok) { echo "  TYPE_MISMATCH ".$name." actual=".$type." expected=".$expected."\n"; $issues++; }
            else echo "  OK ".$name." ".$type."\n";
        }
    }
    $fks=$db->prepare("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('office_crm_opportunities','office_crm_opportunity_activities') AND REFERENCED_TABLE_NAME IS NOT NULL");
    $fks->execute(['u213420663_office_test']);
    foreach ($fks->fetchAll(PDO::FETCH_ASSOC) as $fk) echo "FK ".$fk['TABLE_NAME'].".".$fk['COLUMN_NAME']." -> ".$fk['REFERENCED_TABLE_NAME'].".".$fk['REFERENCED_COLUMN_NAME']."\n";
    echo "CRM_OPPORTUNITY_SCHEMA_DIFF_".($issues===0?'NO_COLUMN_CONFLICTS':'REVIEW_REQUIRED')."\n";
    exit($issues===0?0:3);
} catch (Throwable $e) { fwrite(STDERR,"ABORT: read-only audit failed; no changes made\n"); exit(1); }
