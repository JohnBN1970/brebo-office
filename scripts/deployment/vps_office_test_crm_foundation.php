<?php
declare(strict_types=1);
// VPS Office-test: explicit, checksum-guarded CRM foundation only.
if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0) {fwrite(STDERR, "ROOT_CLI_REQUIRED\n");exit(10);}
$mode=$argv[1]??'';
if (!in_array($mode,['--plan','--apply'],true)) {fwrite(STDERR,"Usage: php vps_office_test_crm_foundation.php --plan|--apply\n");exit(11);}
$release='/srv/brebo/office-test/releases/983e6659447d803d2692d4f26ff2b932d551554f';
if (!is_file($release.'/.office-source-sha') || trim((string)file_get_contents($release.'/.office-source-sha')) !== basename($release)) {fwrite(STDERR,"RELEASE_IDENTITY_MISMATCH\n");exit(12);}
$env='/etc/brebo/office-test-db.env';
if (!is_file($env) || is_link($env) || fileowner($env)!==0) {fwrite(STDERR,"DB_CONFIG_INVALID\n");exit(13);}
$lines=file($env,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
 if (preg_match('/^\s*(?:export\s+)?(OFFICE_TEST_DB_[A-Z_]+)=(.*)$/',$line,$m)) {
  $v=trim($m[2]);if (strlen($v)>=2 && (($v[0]==='"' && substr($v,-1)==='"') || ($v[0]==="'" && substr($v,-1)==="'"))) $v=substr($v,1,-1);
  putenv($m[1].'='.$v);
 }
}
$name=getenv('OFFICE_TEST_DB_NAME');
if ($name!=='brebo_office_test') {fwrite(STDERR,"DB_NAME_REJECTED\n");exit(14);}
$db=new PDO('mysql:host='.(getenv('OFFICE_TEST_DB_HOST')?:'localhost').';dbname='.$name.';charset=utf8mb4',getenv('OFFICE_TEST_DB_USER'),getenv('OFFICE_TEST_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$classes=[
 'Crm/OfficeCrmRelationsSchema.php'=>'Brebo\\Office\\Crm\\OfficeCrmRelationsSchema',
 'Crm/OfficeCrmRepresentationSchema.php'=>'Brebo\\Office\\Crm\\OfficeCrmRepresentationSchema',
 'Crm/OfficeCrmOpportunitySchema.php'=>'Brebo\\Office\\Crm\\OfficeCrmOpportunitySchema',
];
$statements=[];
foreach($classes as $file=>$class) {
 require_once $release.'/src/'.$file;
 foreach($class::statements() as $sql) {
  if (!preg_match('/^CREATE TABLE IF NOT EXISTS (office_crm_[a-z_]+)\s*\(/',$sql,$m)) throw new RuntimeException('UNREVIEWED_DDL');
  $statements[$m[1]]=$sql;
 }
}
$existing=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
if (array_diff($existing,['office_development_migrations'])) throw new RuntimeException('NONEMPTY_DATABASE_REQUIRES_REVIEW');
echo 'CRM_FOUNDATION_TABLES='.count($statements).PHP_EOL;
foreach($statements as $table=>$sql) echo 'PLAN_TABLE='.$table.' SHA256='.hash('sha256',$sql).PHP_EOL;
if ($mode==='--plan') {echo "PLAN_ONLY_NO_WRITES=YES\n";exit(0);}
$db->exec("CREATE TABLE IF NOT EXISTS office_development_migrations (migration_id VARCHAR(190) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
foreach($statements as $table=>$sql) {
 $id='vps-crm-foundation-'.$table;
 $hash=hash('sha256',$sql);
 $q=$db->prepare('SELECT checksum FROM office_development_migrations WHERE migration_id=?');$q->execute([$id]);
 $previous=$q->fetchColumn();
 if ($previous!==false && !hash_equals((string)$previous,$hash)) throw new RuntimeException('CHECKSUM_MISMATCH='.$table);
 $db->exec($sql);
 $q=$db->prepare('INSERT IGNORE INTO office_development_migrations (migration_id,checksum) VALUES (?,?)');$q->execute([$id,$hash]);
 $db->query('SELECT 1 FROM `'.$table.'` LIMIT 0');
 echo 'VERIFIED_TABLE='.$table.PHP_EOL;
}
echo "CRM_FOUNDATION_APPLIED=YES\n";
