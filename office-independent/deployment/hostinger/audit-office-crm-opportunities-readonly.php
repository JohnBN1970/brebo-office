<?php
declare(strict_types=1);
/**
 * Read-only Office-test schema preflight. Never runs DDL.
 * Run from CLI with explicit isolated Office-test configuration.
 */
if (PHP_SAPI !== 'cli' || getenv('OFFICE_BASE_PATH') !== '/office-test') {
    fwrite(STDERR,"ABORT: office-test CLI only\n"); exit(2);
}
$root='/home/u213420663/domains/brebobv.nl/office_calc_test';
$path=getenv('OFFICE_TEST_CONFIG_PATH');
if ($path!==$root.'/config/config.php' || !is_file($path)) {
    fwrite(STDERR,"ABORT: isolated configuration required\n"); exit(2);
}
$config=require $path;
if (!is_array($config) || ($config['OFFICE_BASE_PATH']??null)!=='/office-test') {
    fwrite(STDERR,"ABORT: wrong environment\n"); exit(2);
}
$dsn=$config['OFFICE_INTAKE_PDO_DSN']??null;
$user=$config['OFFICE_INTAKE_DB_USER']??null;
$password=$config['OFFICE_INTAKE_DB_PASSWORD']??null;
if (!is_string($dsn) || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn)
    || $user!=='u213420663_office_test' || !is_string($password) || $password==='') {
    fwrite(STDERR,"ABORT: database scope mismatch\n"); exit(2);
}
try {
    $db=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($db->query('SELECT DATABASE()')->fetchColumn()!=='u213420663_office_test') throw new RuntimeException('wrong_database');
    $stmt=$db->prepare("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,COLUMN_KEY,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('office_crm_relations','office_crm_contact_people','office_crm_opportunities','office_crm_opportunity_activities')");
    $stmt->execute(['u213420663_office_test']);
    $cols=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $cols[$row['TABLE_NAME']][$row['COLUMN_NAME']]=$row;
    foreach (['office_crm_relations','office_crm_contact_people'] as $parent) {
        $id=$cols[$parent]['id']??null;
        if (!$id || !preg_match('/^bigint(?:\\(20\\))? unsigned$/i',$id['COLUMN_TYPE']) || $id['COLUMN_KEY']!=='PRI' || $id['IS_NULLABLE']!=='NO') {
            throw new RuntimeException('parent_id_unverified:'.$parent);
        }
    }
    $tables=$db->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('office_crm_relations','office_crm_contact_people','office_crm_opportunities','office_crm_opportunity_activities')");
    $tables->execute(['u213420663_office_test']);
    $engines=[];
    foreach ($tables->fetchAll(PDO::FETCH_ASSOC) as $row) $engines[$row['TABLE_NAME']]=$row['ENGINE'];
    foreach (['office_crm_relations','office_crm_contact_people'] as $parent) {
        if (($engines[$parent]??null)!=='InnoDB') throw new RuntimeException('parent_engine_unverified:'.$parent);
    }
    foreach (['office_crm_opportunities','office_crm_opportunity_activities'] as $target) {
        if (isset($engines[$target])) {
            echo $target.": ALREADY_PRESENT - inspect before any installation\n";
        } else {
            echo $target.": ABSENT - additive installation candidate\n";
        }
    }
    echo "CRM_OPPORTUNITY_PREFLIGHT_READONLY_OK\n";
} catch (Throwable $e) {
    fwrite(STDERR,"ABORT: schema preflight failed (no changes made)\n"); exit(1);
}
