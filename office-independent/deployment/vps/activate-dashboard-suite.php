<?php
declare(strict_types=1);
/** Explicit privileged activation for the existing isolated VPS PHP-FPM pool. */
if(PHP_SAPI!=='cli'||!function_exists('posix_geteuid')||posix_geteuid()!==0){fwrite(STDERR,"ROOT_CLI_REQUIRED\n");exit(10);}
$mode=$argv[1]??'';
if(!in_array($mode,['--plan','--apply','--workspace-plan','--workspace-apply'],true)){fwrite(STDERR,"Use --plan, --apply, --workspace-plan or --workspace-apply\n");exit(11);}
$workspace=str_starts_with($mode,'--workspace-');
set_exception_handler(static function(Throwable $e):void{fwrite(STDERR,'DASHBOARD_ACTIVATION_FAILED='.($e instanceof RuntimeException?$e->getMessage():get_class($e))."\n");exit(1);});
function demandDashboard(bool $ok,string $reason):void{if(!$ok)throw new RuntimeException($reason);}
function rootDashboardFile(string $path):string{
  clearstatcache(true,$path);
  demandDashboard(is_file($path)&&!is_link($path)&&fileowner($path)===0&&(fileperms($path)&0077)===0,'ROOT_PRIVATE_FILE_REQUIRED');
  $s=file_get_contents($path);demandDashboard(is_string($s),'CONFIG_READ_FAILED');return $s;
}
$base='/srv/brebo/office-test';$release=realpath($base.'/current');
demandDashboard(is_string($release)&&preg_match('~^/srv/brebo/office-test/releases/[a-f0-9]{40}$~D',$release)===1,'TEST_RELEASE_REQUIRED');
demandDashboard(trim((string)file_get_contents($release.'/.office-source-sha'))===basename($release),'RELEASE_IDENTITY_MISMATCH');
$pool='/etc/php/8.3/fpm/pool.d/brebo-office-test.conf';$original=rootDashboardFile($pool);
demandDashboard(preg_match('/^\[brebo-office-test\]\s*$/m',$original)===1,'TEST_POOL_REQUIRED');
demandDashboard(preg_match_all('/^\[[^\]]+\]/m',$original)===1,'SINGLE_TEST_POOL_REQUIRED');
demandDashboard(preg_match('/^env\[OFFICE_VPS_TEST_ROUTER_ENABLED\]\s*=\s*["\x27]?enabled["\x27]?\s*$/m',$original)===1,'EXISTING_ROUTER_ACTIVATION_REQUIRED');
$values=[];
foreach(explode("\n",rootDashboardFile('/etc/brebo/office-test-db.env')) as $line){
  if(!preg_match('/^\s*(?:export\s+)?(OFFICE_TEST_DB_[A-Z_]+)=(.*)$/D',$line,$m))continue;
  $value=trim($m[2]);
  if(strlen($value)>=2&&in_array($value[0],['"',"'"],true)&&substr($value,-1)===$value[0])$value=substr($value,1,-1);
  $values[$m[1]]=$value;
}
demandDashboard(($values['OFFICE_TEST_DB_NAME']??'')==='brebo_office_test'&&($values['OFFICE_TEST_DB_USER']??'')==='brebo_office_test'&&in_array($values['OFFICE_TEST_DB_HOST']??'',['localhost','127.0.0.1'],true),'ISOLATED_DATABASE_REQUIRED');
try{$db=new PDO('mysql:host='.$values['OFFICE_TEST_DB_HOST'].';dbname=brebo_office_test;charset=utf8mb4',$values['OFFICE_TEST_DB_USER'],$values['OFFICE_TEST_DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);}
catch(Throwable){throw new RuntimeException('TEST_DATABASE_CONNECTION_FAILED');}
demandDashboard($db->query('SELECT DATABASE()')->fetchColumn()==='brebo_office_test','DATABASE_IDENTITY_MISMATCH');
foreach(['office_operator_accounts','office_operator_sessions','office_projects','office_project_memberships'] as $table){$db->query('SELECT 1 FROM '.$table.' LIMIT 0');}
require_once $release.'/src/Dashboard/OfficeDashboardPreferences.php';
$sql=\Brebo\Office\Dashboard\OfficeDashboardPreferences::schema();
$checksum=hash('sha256',$sql);
$check=$db->prepare('SELECT checksum FROM office_development_migrations WHERE migration_id=?');
$check->execute(['vps-dashboard-preferences-v1']);$prior=$check->fetchColumn();
demandDashboard($prior===false||hash_equals((string)$prior,$checksum),'MIGRATION_CHECKSUM_CHANGED');
$q=$db->prepare("SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_dashboard_preferences' ORDER BY ORDINAL_POSITION");$q->execute();$columns=$q->fetchAll(PDO::FETCH_KEY_PAIR);
if($columns!==[])demandDashboard(array_keys($columns)===['operator_id','layout_json','updated_at']&&str_contains($columns['operator_id'],'unsigned')&&$columns['layout_json']==='text','PREFERENCE_SCHEMA_MISMATCH');
echo 'VPS_DASHBOARD_RELEASE='.basename($release)."\n";
echo 'VPS_DASHBOARD_SCHEMA='.($columns===[]?'CREATE_ADDITIVE':'PRESENT')."\n";
echo "VPS_DASHBOARD_FLAGS=PERSONAL_DASHBOARD,PREFERENCES_SCHEMA,NAV_V2,UI_V2\n";
$workspaceFlags=[];
if($workspace){
  // Verify the existing project foundation before exposing its routes. No grants,
  // schema, accounts or business records are created by workspace activation.
  foreach([
    'office_projects'=>'id,code,title,status',
    'office_project_memberships'=>'project_id,operator_id,permission',
    'office_operator_accounts'=>'id,active',
    'office_project_buildings'=>'project_id,building_id',
    'office_buildings'=>'id,building_name,street_address,postal_code,city,active',
  ] as $table=>$fields){
    try{$db->query('SELECT '.$fields.' FROM '.$table.' LIMIT 0');}
    catch(Throwable){throw new RuntimeException('WORKSPACE_SCHEMA_MISSING_'.$table);}
  }
  $workspaceFlags=['OFFICE_NATIVE_PROJECTS_ENABLED'=>'enabled','OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED'=>'enabled'];
  echo "VPS_WORKSPACE_PROJECT_SCHEMA=VERIFIED\n";
  echo "VPS_WORKSPACE_FLAGS=NATIVE_PROJECTS,NATIVE_PROJECTS_SCHEMA\n";
  echo "CRM_CALC_INSPECTION_WRITES=UNCHANGED\n";
}
if(in_array($mode,['--plan','--workspace-plan'],true)){echo "PLAN_ONLY_NO_WRITES\n";exit;}
if(!$workspace){
  // Dashboard-only schema changes must never run during workspace activation.
  $db->exec($sql);$db->query('SELECT operator_id,layout_json,updated_at FROM office_dashboard_preferences LIMIT 0');
  $record=$db->prepare('INSERT IGNORE INTO office_development_migrations(migration_id,checksum) VALUES (?,?)');
  $record->execute(['vps-dashboard-preferences-v1',$checksum]);
}
$new=$original;
$flags=['OFFICE_PERSONAL_DASHBOARD_ENABLED'=>'enabled','OFFICE_DASHBOARD_PREFERENCES_SCHEMA_VERIFIED'=>'enabled','OFFICE_TEST_NAV_V2_ENABLED'=>'enabled','OFFICE_TEST_UI_V2_ROLLOUT_ALLOWED'=>'enabled'];
$flags=$workspace?$workspaceFlags:$flags;
if(!$workspace && !preg_match('/^env\[OFFICE_DASHBOARD_CSRF_SECRET\]\s*=\s*["\x27]?[a-f0-9]{64}["\x27]?\s*$/m',$original))$flags['OFFICE_DASHBOARD_CSRF_SECRET']=bin2hex(random_bytes(32));
foreach($flags as $key=>$value){
  $pattern='/^env\['.preg_quote($key,'/').'\]\s*=.*$/m';$line='env['.$key.'] = '.$value;
  if(preg_match($pattern,$new))$new=preg_replace($pattern,$line,$new);else $new=rtrim($new)."\n".$line."\n";
}
$backup=$pool.'.dashboard-backup-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
demandDashboard(file_put_contents($backup,$original,LOCK_EX)!==false&&chmod($backup,0600),'BACKUP_FAILED');
$tmp=tempnam(dirname($pool),'.dashboard-');demandDashboard(is_string($tmp),'TEMP_FAILED');chmod($tmp,0600);
demandDashboard(file_put_contents($tmp,$new,LOCK_EX)!==false,'CONFIG_WRITE_FAILED');
demandDashboard(rename($tmp,$pool),'CONFIG_RENAME_FAILED');
exec('php-fpm8.3 -t >/dev/null 2>&1',$output,$code);
if($code!==0){copy($backup,$pool);chmod($pool,0600);throw new RuntimeException('FPM_VALIDATION_FAILED_RESTORED');}
exec('systemctl reload php8.3-fpm >/dev/null 2>&1',$output,$code);
if($code!==0){copy($backup,$pool);chmod($pool,0600);exec('systemctl reload php8.3-fpm >/dev/null 2>&1');throw new RuntimeException('FPM_RELOAD_FAILED_RESTORED');}
echo "VPS_DASHBOARD_ACTIVATED\n";
if($workspace)echo "VPS_WORKSPACE_PROJECTS_ACTIVATED\n";
echo "NEXT=authenticated dashboard, builder and wallboard acceptance on https://office-test.brebobv.nl/office-test/mijn-dashboard\n";
