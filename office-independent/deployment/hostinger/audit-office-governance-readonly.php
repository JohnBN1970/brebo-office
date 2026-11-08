<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('OFFICE_GOVERNANCE_PREFLIGHT') !== 'read-only' || getenv('OFFICE_BASE_PATH') !== '/office-test') { fwrite(STDERR,"SCOPE_DENIED\n"); exit(2); }
$root='/home/u213420663/domains/brebobv.nl/office_calc_test';
$configPath=$root.'/config/config.php';
if (!is_file($configPath)) { fwrite(STDERR,"CONFIG_MISSING\n"); exit(2); }
$config=require $configPath;
if (!is_array($config) || ($config['OFFICE_BASE_PATH']??null)!=='/office-test') { fwrite(STDERR,"CONFIG_SCOPE_DENIED\n"); exit(2); }
$dsn=$config['OFFICE_INTAKE_PDO_DSN']??null;
$user=$config['OFFICE_INTAKE_DB_USER']??null;
$pass=$config['OFFICE_INTAKE_DB_PASSWORD']??null;
if (!is_string($dsn) || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn) || $user!=='u213420663_office_test' || !is_string($pass)) { fwrite(STDERR,"DB_SCOPE_DENIED\n"); exit(2); }
try {
$db=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
if ($db->query('SELECT DATABASE()')->fetchColumn()!=='u213420663_office_test') throw new RuntimeException('database_scope');
$tables=['office_projects','office_buildings','office_project_buildings','office_quality_checks','office_risks','office_procurement_orders','office_governance_commands','office_governance_audit'];
$q=$db->prepare('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
$c=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
foreach($tables as $table) {
$q->execute(['u213420663_office_test',$table]);$row=$q->fetch(PDO::FETCH_ASSOC);
if(!$row){echo "$table: ABSENT\n";continue;}
echo "$table: PRESENT engine=".$row['ENGINE']."\n";
$c->execute(['u213420663_office_test',$table]);
foreach($c->fetchAll(PDO::FETCH_ASSOC) as $col) {
if(in_array($col['COLUMN_NAME'],['id','project_id','building_id'],true)) echo '  '.$col['COLUMN_NAME'].' '.$col['COLUMN_TYPE'].' '.$col['COLUMN_KEY'].' nullable='.$col['IS_NULLABLE']."\n";
}
}
echo "OFFICE_GOVERNANCE_READONLY_PREFLIGHT_COMPLETE\n";
} catch(Throwable $e){fwrite(STDERR,"PREFLIGHT_FAILED: ".$e->getMessage()."\n");exit(1);}
