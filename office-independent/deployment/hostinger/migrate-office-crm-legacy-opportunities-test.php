<?php
declare(strict_types=1);
/**
 * Explicit Office-test legacy CRM opportunity migration. Never drops/renames tables or columns.
 * Requires a separate backup and operator authorization. MySQL DDL auto-commits.
 */
if (PHP_SAPI!=='cli' || getenv('OFFICE_BASE_PATH')!=='/office-test'
    || getenv('OFFICE_CRM_LEGACY_MIGRATION')!=='explicit') {
    fwrite(STDERR,"ABORT: explicit Office-test migration approval required\n"); exit(2);
}
$root='/home/u213420663/domains/brebobv.nl/office_calc_test';
$path=getenv('OFFICE_TEST_CONFIG_PATH');
if ($path!==$root.'/config/config.php' || !is_file($path)) {
    fwrite(STDERR,"ABORT: isolated config required\n"); exit(2);
}
$c=require $path;
$dsn=$c['OFFICE_INTAKE_PDO_DSN']??null;
$user=$c['OFFICE_INTAKE_DB_USER']??null;
$pass=$c['OFFICE_INTAKE_DB_PASSWORD']??null;
if (($c['OFFICE_BASE_PATH']??null)!=='/office-test' || !is_string($dsn)
    || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn)
    || $user!=='u213420663_office_test' || !is_string($pass) || $pass==='') {
    fwrite(STDERR,"ABORT: database scope mismatch\n"); exit(2);
}
try {
    $db=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($db->query('SELECT DATABASE()')->fetchColumn()!=='u213420663_office_test') throw new RuntimeException('wrong_database');
    $schema='u213420663_office_test';
    $q=$db->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('office_crm_opportunities','office_crm_opportunity_activities','office_crm_relations','office_crm_contact_people')");
    $q->execute([$schema]); $eng=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (['office_crm_opportunities','office_crm_relations','office_crm_contact_people'] as $table)
        if (($eng[$table]??null)!=='InnoDB') throw new RuntimeException('missing_or_non_innodb:'.$table);
    if (isset($eng['office_crm_opportunity_activities'])) throw new RuntimeException('activities_already_present_inspect_first');
    $q=$db->prepare("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('office_crm_opportunities','office_crm_relations','office_crm_contact_people')");
    $q->execute([$schema]); $cols=[];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $cols[$row['TABLE_NAME']][$row['COLUMN_NAME']]=$row;
    foreach (['office_crm_opportunities','office_crm_relations','office_crm_contact_people'] as $table) {
        $id=$cols[$table]['id']??null;
        if (!$id || !preg_match('/^bigint(?:\\(20\\))? unsigned$/i',$id['COLUMN_TYPE'])
            || $id['IS_NULLABLE']!=='NO' || $id['COLUMN_KEY']!=='PRI') throw new RuntimeException('invalid_id:'.$table);
    }
    $expected=['title'=>'varchar(255)','stage'=>'varchar(32)','source'=>'varchar(128)','estimated_value'=>'decimal(15,2)','currency'=>'char(3)','created_at'=>'timestamp','updated_at'=>'timestamp'];
    foreach ($expected as $name=>$type) if (strtolower($cols['office_crm_opportunities'][$name]['COLUMN_TYPE']??'')!==$type)
        throw new RuntimeException('legacy_column_unexpected:'.$name);
    $add=[
        'relation_id'=>'BIGINT UNSIGNED NULL',
        'contact_id'=>'BIGINT UNSIGNED NULL',
        'description'=>'TEXT NULL',
        'expected_value'=>'DECIMAL(14,2) NULL',
        'expected_close_date'=>'DATE NULL',
        'next_action'=>'VARCHAR(255) NULL',
        'next_action_at'=>'DATETIME NULL',
        'opname_required'=>'TINYINT(1) NOT NULL DEFAULT 0',
        'status'=>"VARCHAR(16) NOT NULL DEFAULT 'open'"
    ];
    foreach ($add as $name=>$definition) if (isset($cols['office_crm_opportunities'][$name]))
        throw new RuntimeException('partial_migration_inspect_first:'.$name);
    $n=(int)$db->query('SELECT COUNT(*) FROM office_crm_opportunities')->fetchColumn();
    if ($n!==0) throw new RuntimeException('legacy_rows_present_manual_mapping_required');
    $q=$db->prepare("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND TABLE_NAME='office_crm_opportunities' AND REFERENCED_TABLE_NAME IS NOT NULL");
    $q->execute([$schema]);
    if ((int)$q->fetchColumn()!==0) throw new RuntimeException('unexpected_existing_outbound_fk');
    echo "CRM_LEGACY_MIGRATION_PREFLIGHT_OK\n";
    foreach ($add as $name=>$definition) {
        $db->exec("ALTER TABLE office_crm_opportunities ADD COLUMN ".$name." ".$definition);
        echo "ADDED ".$name."\n";
    }
    $db->exec('ALTER TABLE office_crm_opportunities ADD INDEX idx_crm_opp_relation (relation_id)');
    $db->exec('ALTER TABLE office_crm_opportunities ADD INDEX idx_crm_opp_stage (stage,status)');
    $db->exec('ALTER TABLE office_crm_opportunities ADD INDEX idx_crm_opp_calendar (next_action_at)');
    $db->exec('ALTER TABLE office_crm_opportunities ADD CONSTRAINT fk_crm_opp_relation FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id)');
    $db->exec('ALTER TABLE office_crm_opportunities ADD CONSTRAINT fk_crm_opp_contact FOREIGN KEY (contact_id) REFERENCES office_crm_contact_people(id)');
    require_once dirname(__DIR__,2).'/src/Crm/OfficeCrmOpportunitySchema.php';
    $sql=\Brebo\Office\Crm\OfficeCrmOpportunitySchema::statements();
    if (count($sql)!==2) throw new RuntimeException('unexpected_activity_schema');
    $db->exec($sql[1]);
    $q=$db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='office_crm_opportunity_activities'");
    $q->execute([$schema]);
    if ((int)$q->fetchColumn()<6) throw new RuntimeException('activity_verification_failed');
    echo "CRM_LEGACY_MIGRATION_COMPLETE\n";
} catch (Throwable $e) {
    fwrite(STDERR,"ABORT: migration blocked or interrupted. Do not rerun; inspect schema and backup.\n");
    exit(1);
}
