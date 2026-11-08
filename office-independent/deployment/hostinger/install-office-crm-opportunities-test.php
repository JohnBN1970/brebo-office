<?php
declare(strict_types=1);
/** Explicit additive Office-test-only installer. DDL auto-commits: never retry partial installs blindly. */
if (PHP_SAPI!=='cli' || getenv('OFFICE_BASE_PATH')!=='/office-test' || getenv('OFFICE_CRM_OPPORTUNITIES_SCHEMA_INSTALL')!=='explicit') {
    fwrite(STDERR,"ABORT: explicit isolated test approval required\n"); exit(2);
}
$root='/home/u213420663/domains/brebobv.nl/office_calc_test';
$path=getenv('OFFICE_TEST_CONFIG_PATH');
if ($path!==$root.'/config/config.php' || !is_file($path)) {
    fwrite(STDERR,"ABORT: isolated configuration missing\n"); exit(2);
}
$config=require $path;
$dsn=$config['OFFICE_INTAKE_PDO_DSN']??null;
$user=$config['OFFICE_INTAKE_DB_USER']??null;
$password=$config['OFFICE_INTAKE_DB_PASSWORD']??null;
if (($config['OFFICE_BASE_PATH']??null)!=='/office-test'
    || !is_string($dsn) || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn)
    || $user!=='u213420663_office_test' || !is_string($password) || $password==='') {
    fwrite(STDERR,"ABORT: database scope mismatch\n"); exit(2);
}
try {
    $db=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($db->query('SELECT DATABASE()')->fetchColumn()!=='u213420663_office_test') throw new RuntimeException('wrong_database');
    $schema='u213420663_office_test';
    $parents=['office_crm_relations','office_crm_contact_people'];
    $targets=['office_crm_opportunities','office_crm_opportunity_activities'];
    $stmt=$db->prepare("SELECT TABLE_NAME,COLUMN_TYPE,COLUMN_KEY,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND COLUMN_NAME='id' AND TABLE_NAME IN ('office_crm_relations','office_crm_contact_people')");
    $stmt->execute([$schema]);
    $ids=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $ids[$row['TABLE_NAME']]=$row;
    $stmt=$db->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('office_crm_relations','office_crm_contact_people','office_crm_opportunities','office_crm_opportunity_activities')");
    $stmt->execute([$schema]);
    $engines=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $engines[$row['TABLE_NAME']]=$row['ENGINE'];
    foreach ($parents as $table) {
        $id=$ids[$table]??null;
        if (!$id || !preg_match('/^bigint(?:\\(20\\))? unsigned$/i',$id['COLUMN_TYPE']) || $id['COLUMN_KEY']!=='PRI'
            || $id['IS_NULLABLE']!=='NO' || ($engines[$table]??null)!=='InnoDB') throw new RuntimeException('unverified_parent:'.$table);
    }
    foreach ($targets as $table) if (isset($engines[$table])) throw new RuntimeException('target_exists_inspect_first:'.$table);
    require_once dirname(__DIR__,2).'/src/Crm/OfficeCrmOpportunitySchema.php';
    $statements=\Brebo\Office\Crm\OfficeCrmOpportunitySchema::statements();
    if (count($statements)!==2) throw new RuntimeException('unexpected_schema_count');
    echo "CRM_OPPORTUNITIES_PREFLIGHT_OK\n";
    foreach ($statements as $sql) $db->exec($sql);
    foreach ($targets as $table) {
        $check=$db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
        $check->execute([$schema,$table]);
        if ((int)$check->fetchColumn()<5) throw new RuntimeException('installation_verification_failed:'.$table);
        echo $table.": PRESENT\n";
    }
    echo "CRM_OPPORTUNITIES_SCHEMA_INSTALL_COMPLETE\n";
} catch (Throwable $e) {
    fwrite(STDERR,"ABORT: installation failed or blocked; inspect state before retry\n"); exit(1);
}
