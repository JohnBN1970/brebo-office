<?php
declare(strict_types=1);
// Isolated VPS Office-test building identity foundation, never production.
if (PHP_SAPI!=='cli' || !function_exists('posix_geteuid') || posix_geteuid()!==0) {fwrite(STDERR,"ROOT_CLI_REQUIRED\n");exit(10);}
$mode=$argv[1]??'';
if (!in_array($mode,['--plan','--apply'],true)) {fwrite(STDERR,"USAGE: --plan|--apply\n");exit(11);}
$sha='983e6659447d803d2692d4f26ff2b932d551554f';
$release='/srv/brebo/office-test/releases/'.$sha;
if (trim((string)@file_get_contents($release.'/.office-source-sha'))!==$sha) {fwrite(STDERR,"RELEASE_IDENTITY_MISMATCH\n");exit(12);}
$env='/etc/brebo/office-test-db.env';
if (!is_file($env)||is_link($env)||fileowner($env)!==0) {fwrite(STDERR,"DB_ENV_INVALID\n");exit(13);}
foreach(file($env,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
 if(preg_match('/^\s*(?:export\s+)?(OFFICE_TEST_DB_[A-Z_]+)=(.*)$/',$line,$m)) {
  $v=trim($m[2]);if(strlen($v)>1 && (($v[0]==='"'&&substr($v,-1)==='"')||($v[0]==="'"&&substr($v,-1)==="'"))) $v=substr($v,1,-1);
  putenv($m[1].'='.$v);
 }
}
if(getenv('OFFICE_TEST_DB_NAME')!=='brebo_office_test') throw new RuntimeException('DATABASE_SCOPE_REJECTED');
$db=new PDO('mysql:host='.(getenv('OFFICE_TEST_DB_HOST')?:'localhost').';dbname=brebo_office_test;charset=utf8mb4',getenv('OFFICE_TEST_DB_USER'),getenv('OFFICE_TEST_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$lock=$db->query("SELECT GET_LOCK('brebo_office_test_building_foundation',5)")->fetchColumn();
if((int)$lock!==1) throw new RuntimeException('MIGRATION_LOCK_UNAVAILABLE');
try {
 require_once $release.'/src/Buildings/OfficeBuildingSchema.php';
 $statements=\Brebo\Office\Buildings\OfficeBuildingSchema::statements();
 $tables=['office_buildings','office_project_buildings'];
 if(count($statements)!==2) throw new RuntimeException('SCHEMA_CHANGED');
 foreach($statements as $i=>$sql) {
  if(!preg_match('/^CREATE TABLE '.preg_quote($tables[$i],'/').'\s*\(/',$sql)) throw new RuntimeException('UNREVIEWED_DDL');
 }
 $existing=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
 foreach($tables as $table) if(in_array($table,$existing,true)) throw new RuntimeException('BUILDING_TABLE_ALREADY_EXISTS_REVIEW='.$table);
 $parent=$db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_crm_relations' AND COLUMN_NAME='id'")->fetchColumn();
 if(!preg_match('/^bigint(?:\\(20\\))? unsigned$/i',(string)$parent)) throw new RuntimeException('CRM_FOUNDATION_NOT_VERIFIED');
 foreach($statements as $i=>$sql) echo 'PLAN_TABLE='.$tables[$i].' SHA256='.hash('sha256',$sql).PHP_EOL;
 if($mode==='--plan'){echo "BUILDINGS_PLAN_ONLY=YES\n";exit(0);}
 foreach($statements as $i=>$sql) {
  $db->exec($sql);
  $db->query('SELECT 1 FROM `'.$tables[$i].'` LIMIT 0');
  echo 'VERIFIED_TABLE='.$tables[$i].PHP_EOL;
 }
 echo "BUILDINGS_FOUNDATION_APPLIED=YES\n";
} finally {$db->query("SELECT RELEASE_LOCK('brebo_office_test_building_foundation')");}
