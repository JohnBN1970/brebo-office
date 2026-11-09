<?php
declare(strict_types=1);

// CLI only. Default is a read-only plan. Never included by a request or deploy.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/src/Buildings/OfficeBuildingPdokSchemaMigration.php';
use Brebo\Office\Buildings\OfficeBuildingPdokSchemaMigration as Migration;

function requirePdok(bool $ok, string $reason): void { if (!$ok) throw new RuntimeException($reason); }
function connectPdok(array $config): PDO {
  return new PDO($config['OFFICE_INTAKE_PDO_DSN'], $config['OFFICE_INTAKE_DB_USER'], $config['OFFICE_INTAKE_DB_PASSWORD'],
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
}
function columnsPdok(PDO $db, string $table): array {
  $q=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
  $q->execute([$table]);
  $columns=$q->fetchAll(PDO::FETCH_COLUMN);
  requirePdok($columns!==[], 'required_table_missing');
  foreach ($columns as $column) requirePdok((bool)preg_match('/^[a-zA-Z0-9_]+$/D',$column), 'unexpected_column_name');
  return $columns;
}
function digestPdok(PDO $db, string $table, array $columns): array {
  $list=implode(',',array_map(static fn($c)=>'`'.$c.'`',$columns));
  $q=$db->query('SELECT '.$list.' FROM `'.$table.'` ORDER BY '.$list);
  $hash=hash_init('sha256'); $count=0;
  while (($row=$q->fetch(PDO::FETCH_NUM))!==false) {
    hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n"); $count++;
  }
  return ['count'=>$count,'sha256'=>hash_final($hash)];
}
try {
  $args=getopt('', ['config:', 'apply', 'backup:', 'sha256:', 'restore-config:', 'confirm:', 'writers-paused:']);
  $base=realpath((getenv('HOME') ?: '').'/domains/brebobv.nl/office_calc_test');
  requirePdok(is_string($base) && is_dir($base), 'office_test_root_missing');
  $path=realpath((string)($args['config']??''));
  requirePdok($path===$base.'/config/config.php', 'office_test_config_required');
  $config=require $path;
  requirePdok(is_array($config) && ($config['OFFICE_BASE_PATH']??null)==='/office-test', 'office_test_mount_required');
  requirePdok(in_array($config['OFFICE_INTAKE_PDO_DSN']??null,[
    'mysql:host=localhost;dbname=u213420663_office_test;charset=utf8mb4',
    'mysql:host=127.0.0.1;dbname=u213420663_office_test;charset=utf8mb4'],true), 'office_test_dsn_required');
  $db=connectPdok($config);
  requirePdok($db->query('SELECT DATABASE()')->fetchColumn()==='u213420663_office_test','database_identity_mismatch');
  $missing=Migration::inspect($db);
  echo json_encode(['mode'=>isset($args['apply'])?'guarded-apply':'read-only-plan','missing'=>array_keys($missing)],JSON_THROW_ON_ERROR)."\n";
  if (!isset($args['apply'])) exit(0);
  requirePdok(($args['confirm']??null)==='OFFICE-TEST-ADD-PDOK-COLUMNS'
    && ($args['writers-paused']??null)==='OFFICE-TEST', 'explicit_approval_and_paused_writers_required');
  $backup=realpath((string)($args['backup']??''));
  $backupDir=realpath($base.'/backups/pdok');
  requirePdok(is_string($backupDir) && is_string($backup) && str_starts_with($backup,$backupDir.'/')
    && is_file($backup) && str_ends_with($backup,'.sql.gz'), 'private_pdok_backup_required');
  requirePdok((fileperms($backup)&0077)===0 && (fileperms($backupDir)&0077)===0,'private_backup_permissions_required');
  $expected=(string)($args['sha256']??'');
  requirePdok((bool)preg_match('/^[a-f0-9]{64}$/D',$expected) && hash_equals($expected,hash_file('sha256',$backup)), 'backup_checksum_mismatch');
  requirePdok(filemtime($backup)>=time()-3600 && filemtime($backup)<=time(), 'fresh_backup_required');
  $process=proc_open(['gzip','-t','--',$backup],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
  requirePdok(is_resource($process) && proc_close($process)===0,'backup_gzip_integrity_failed');
  // Isolated restore is an operator step in the runbook; this tool
  // verifies the restored building, zone and link data itself before any DDL.
  $restorePath=realpath((string)($args['restore-config']??''));
  requirePdok(is_string($restorePath) && str_starts_with($restorePath,$base.'/config/') && $restorePath!==$path, 'private_restore_config_required');
  $restoreConfig=require $restorePath;
  requirePdok(is_array($restoreConfig) && in_array($restoreConfig['OFFICE_INTAKE_PDO_DSN']??null,[
    'mysql:host=localhost;dbname=u213420663_office_restore;charset=utf8mb4',
    'mysql:host=127.0.0.1;dbname=u213420663_office_restore;charset=utf8mb4'],true), 'isolated_restore_database_required');
  $restore=connectPdok($restoreConfig);
  requirePdok($restore->query('SELECT DATABASE()')->fetchColumn()==='u213420663_office_restore','restore_identity_mismatch');
  requirePdok((int)$db->query("SELECT GET_LOCK('office_pdok_schema_migration',0)")->fetchColumn()===1, 'migration_already_running');
  $before=[]; $columns=[];
  foreach (['office_buildings','office_building_zones','office_project_buildings'] as $table) {
    $columns[$table]=columnsPdok($db,$table);
    requirePdok($columns[$table]===columnsPdok($restore,$table),'restored_columns_differ');
    $before[$table]=digestPdok($db,$table,$columns[$table]);
    requirePdok($before[$table]===digestPdok($restore,$table,$columns[$table]),'restored_data_differ');
  }
  $applied=Migration::apply($db);
  foreach ($before as $table=>$snapshot) requirePdok($snapshot===digestPdok($db,$table,$columns[$table]),'post_migration_data_mismatch_stop');
  requirePdok(Migration::inspect($db)===[], 'post_migration_columns_missing');
  echo json_encode(['status'=>'PDOK_MIGRATION_VERIFIED','added'=>$applied,'preserved'=>$before,'backup_sha256'=>$expected],JSON_THROW_ON_ERROR)."\n";
  $db->query("SELECT RELEASE_LOCK('office_pdok_schema_migration')");
} catch (Throwable $error) {
  // DDL is not transactionally reversible. Never drop added columns or restore
  // over the running database automatically. Do not disclose connection data.
  fwrite(STDERR,"PDOK_MIGRATION_STOPPED: ".($error instanceof PDOException ? 'database_operation_failed' : $error->getMessage())."\n");
  exit(1);
}
