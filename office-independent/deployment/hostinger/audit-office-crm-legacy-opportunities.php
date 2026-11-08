<?php
declare(strict_types=1);
/** Read-only inventory: existing opportunity structure and relationship safety. No DDL or row contents. */
if (PHP_SAPI!=='cli' || getenv('OFFICE_BASE_PATH')!=='/office-test') { fwrite(STDERR,"ABORT: Office-test CLI only\n"); exit(2); }
$root='/home/u213420663/domains/brebobv.nl/office_calc_test';
$path=getenv('OFFICE_TEST_CONFIG_PATH');
if ($path!==$root.'/config/config.php' || !is_file($path)) { fwrite(STDERR,"ABORT: isolated config required\n"); exit(2); }
$c=require $path;
$dsn=$c['OFFICE_INTAKE_PDO_DSN']??null;
$user=$c['OFFICE_INTAKE_DB_USER']??null;
$pass=$c['OFFICE_INTAKE_DB_PASSWORD']??null;
if (($c['OFFICE_BASE_PATH']??null)!=='/office-test' || !is_string($dsn) || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn) || $user!=='u213420663_office_test' || !is_string($pass) || $pass==='') { fwrite(STDERR,"ABORT: database scope mismatch\n"); exit(2); }
try {
$db=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
if ($db->query('SELECT DATABASE()')->fetchColumn()!=='u213420663_office_test') throw new RuntimeException('Wrong database');
$tables=['office_crm_opportunities','office_crm_relations','office_crm_contact_people'];
$q=$db->prepare("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_KEY,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('office_crm_opportunities','office_crm_relations','office_crm_contact_people') ORDER BY TABLE_NAME,ORDINAL_POSITION");
$q->execute(['u213420663_office_test']);
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) echo 'COLUMN '.implode(' | ',array_values($r))."\n";
$q=$db->prepare("SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME='office_crm_opportunities' ORDER BY INDEX_NAME,SEQ_IN_INDEX");
$q->execute(['u213420663_office_test']);
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) echo 'INDEX '.implode(' | ',array_values($r))."\n";
$q=$db->prepare("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND (TABLE_NAME='office_crm_opportunities' OR REFERENCED_TABLE_NAME='office_crm_opportunities') AND REFERENCED_TABLE_NAME IS NOT NULL");
$q->execute(['u213420663_office_test']);
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) echo 'FK '.implode(' | ',array_values($r))."\n";
$q=$db->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('office_crm_opportunities','office_crm_relations','office_crm_contact_people')");
$q->execute(['u213420663_office_test']);
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) echo 'ENGINE '.implode(' | ',array_values($r))."\n";
$count=(int)$db->query('SELECT COUNT(*) FROM office_crm_opportunities')->fetchColumn();
echo 'EXISTING_OPPORTUNITY_ROWS '.$count."\n";
echo "CRM_LEGACY_INVENTORY_READONLY_OK\n";
} catch (Throwable $e) { fwrite(STDERR,"ABORT: inventory failed; no changes made\n"); exit(1); }
