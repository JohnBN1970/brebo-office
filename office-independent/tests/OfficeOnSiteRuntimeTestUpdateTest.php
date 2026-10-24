<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/deployment/hostinger/OfficeOnSiteRuntimeTestUpdate.php';
use Brebo\Office\Deployment\OfficeOnSiteRuntimeTestUpdate;

$repo=dirname(__DIR__);
$baseline=json_decode((string)file_get_contents($repo.'/deployment/hostinger/office-onsite-runtime-observed-20261023.json'),true,64,JSON_THROW_ON_ERROR);
// Private uploaded source is never checked into GitHub or published in CI artifacts.
// An optional local fixture validates the exact observed snapshot; CI uses synthetic code.
$privateFixture=getenv('OFFICE_ONSITE_OBSERVED_SOURCE_FIXTURE');
if (is_string($privateFixture) && $privateFixture!=='') {
  $observed=json_decode((string)file_get_contents($privateFixture),true,64,JSON_THROW_ON_ERROR);
  foreach ([...$baseline['old'],...$baseline['protected']] as $path=>$hash) {
    if ($hash!==null && (!isset($observed[$path]) || hash('sha256',$observed[$path])!==$hash)) {
      throw new RuntimeException('Private fixture differs from observed fingerprint');
    }
  }
} else {
  $observed=[];
  foreach ([...$baseline['old'],...$baseline['protected']] as $path=>$hash) {
    if ($hash!==null) $observed[$path]="<?php\n// synthetic previous code: ".$path."\n";
  }
  $observed['src/Project/OfficeNativeProjectAccess.php']=<<<'ACL'
<?php
namespace Brebo\Office\Project;
final class OfficeNativeProjectAccess {
  public function canRead(string $operatorId,int $projectId):bool {return false;}
  public function canManage(string $operatorId,int $projectId):bool {return false;}
}
ACL;
}
$sources=[];
foreach ($baseline['old'] as $path=>$hash) $sources[$path]=(string)file_get_contents($repo.'/'.$path);
$assert=static function(bool $ok,string $reason):void {if (!$ok) throw new RuntimeException($reason);};
$blocked=static function(Closure $call,string $reason) use($assert):void {
  try {$call();} catch (Throwable $e) {$assert(str_contains($e->getMessage(),$reason),'Unexpected block: '.$e->getMessage());return;}
  throw new RuntimeException('Operation unexpectedly allowed: '.$reason);
};
$fixture=static function() use($sources,$baseline,$observed):array {
  $base=sys_get_temp_dir().'/onsite-update-test-'.bin2hex(random_bytes(8));
  $root=$base.'/release/private'; $router=$base.'/http/index.php'; $config=$base.'/config/config.php';
  foreach ([$root.'/src/Inzet',$root.'/src/Intake',$root.'/src/Buildings',$root.'/src/Project',
    $root.'/public',dirname($router),dirname($config)] as $dir) if (!is_dir($dir)) mkdir($dir,0700,true);
  foreach ($observed as $path=>$code) file_put_contents($root.'/'.$path,$code);
  $policy=$baseline;
  foreach ($policy['old'] as $path=>$hash) {
    if ($hash!==null) $policy['old'][$path]=hash('sha256',$observed[$path]);
  }
  foreach ($policy['protected'] as $path=>$hash) {
    $code=$observed[$path];
    file_put_contents($root.'/'.$path,$code);$policy['protected'][$path]=hash('sha256',$code);
  }
  foreach (['OfficeMount','WebsiteCrmOperatorContext','WebsiteCrmOperatorSessionProviderInterface',
    'WebsiteOfficeCrmSessionRepository','WebsiteOfficeCrmCookieSessionProvider'] as $name) {
    file_put_contents($root.'/src/Intake/'.$name.'.php',"<?php\n// existing session helper\n");
  }
  $cfg=['OFFICE_BASE_PATH'=>'/office-test','OFFICE_PRIVATE_ROOT'=>$root,'OFFICE_PUBLIC_HOST'=>'brebobv.nl',
    'OFFICE_INTAKE_DB_USER'=>'u213420663_office_test',
    'OFFICE_INTAKE_PDO_DSN'=>'mysql:host=localhost;dbname=u213420663_office_test;charset=utf8mb4',
    'OFFICE_ONSITE_SCHEMA_VERIFIED'=>'false','OFFICE_ONSITE_ACTIVATION_ENABLED'=>'false',
    'OFFICE_ONSITE_BOOTSTRAP_ENABLED'=>'false','OFFICE_ONSITE_PRESENCE_ENABLED'=>'false',
    'OFFICE_TEST_ZONE_WRITE_ENABLED'=>'enabled','OFFICE_TEST_PRESENCE_REVIEW_ENABLED'=>'enabled',
    'PRIVATE_SENTINEL'=>'never-print-this-config-value'];
  file_put_contents($config,"<?php\nreturn ".var_export($cfg,true).";\n");
  file_put_contents($router,"<?php\n\$root='".$root."';\n\$routes = [\n  '/office-test/inzet/projectkaart' => 'office-onsite-project-map.php',\n  '/office-test/server-only-page' => 'keep.php',\n];\n");
  file_put_contents(dirname($router).'/.htaccess',"AuthType Basic\nAuthName private-test\nRequire valid-user\nRewriteEngine On\nRewriteRule ^projecten/?$ index.php [END,QSA]\n");
  // Unrelated, newer server work must survive both apply and rollback.
  file_put_contents($root.'/src/Inzet/OnSiteHoursReview.php',"<?php\n// server-only hours work\n");
  return [$base,$root,$router,$config,$cfg,$policy];
};
$tree=static function(string $base):array {
  $snapshot=[];
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS)) as $f) {
    $snapshot[substr($f->getPathname(),strlen($base))]=hash_file('sha256',$f->getPathname());
  }
  ksort($snapshot);return $snapshot;
};
$run=static fn(array $f,array $code):OfficeOnSiteRuntimeTestUpdate=>new OfficeOnSiteRuntimeTestUpdate($f[0],$f[1],$f[2],$f[3],$code,$f[5]);
$f=$fixture();$before=$tree($f[0]);
$updater=$run($f,$sources);
$assert($updater->run('--check')['status']==='READY_TO_APPLY','Observed legacy fixture not accepted');
$assert($tree($f[0])===$before,'Check mutated files');
$assert($updater->run('--apply')['status']==='INSTALLED','Apply failed');
foreach ($sources as $path=>$code) $assert(file_get_contents($f[1].'/'.$path)===$code,'Incomplete runtime deployment');
// Load the installed implementations together, including the interface that was a legacy class.
$probe=<<<'PROBE'
spl_autoload_register(static function(string $class):void {
  $prefix='Brebo\\Office\\';
  if (str_starts_with($class,$prefix)) {
    $path=__ROOT__.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if (is_file($path)) require_once $path;
  }
});
foreach (__CLASSES__ as $class) {
  if (!class_exists($class) && !interface_exists($class)) throw new RuntimeException('Installed dependency missing: '.$class);
}
if (!interface_exists('Brebo\\Office\\Inzet\\OnSiteAssignmentRepository')
  || !method_exists('Brebo\\Office\\Inzet\\OnSiteDeviceTokenRepository','resolveEmployee')
  || !method_exists('Brebo\\Office\\Inzet\\OnSiteEventInbox','store')) {
  throw new RuntimeException('Mixed legacy/Office runtime');
}
PROBE;
$classes=[];
foreach (array_keys($sources) as $path) if (str_starts_with($path,'src/Inzet/')) {
  $classes[]='Brebo\\Office\\Inzet\\'.basename($path,'.php');
}
$probe=str_replace(['__ROOT__','__CLASSES__'],[var_export($f[1],true),var_export($classes,true)],$probe);
$process=proc_open([PHP_BINARY,'-c',(string)php_ini_loaded_file(),'-r',$probe],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
if (!is_resource($process)) throw new RuntimeException('Runtime probe unavailable');
$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
$assert(proc_close($process)===0 && $errors==='','Installed runtime composition failed: '.$errors);
$assert(file_get_contents($f[1].'/src/Inzet/OnSiteHoursReview.php')==="<?php\n// server-only hours work\n",'Server-only work overwritten');
$assert(hash_file('sha256',$f[3])===$before['/config/config.php'],'Config modified');
foreach ($f[5]['protected'] as $path=>$hash) $assert(hash_file('sha256',$f[1].'/'.$path)===$hash,'Shared code overwritten');
$routerSource=file_get_contents($f[2]);
$assert(str_contains($routerSource,"'/office-test/server-only-page' => 'keep.php'"),'Existing route lost');
foreach (['activate','bootstrap','presence'] as $endpoint) {
  $assert(str_contains($routerSource,"'/office-test/api/onsite/v1/".$endpoint."' => 'onsite-".$endpoint.".php'"),'API route not installed');
}
 $adapter=file_get_contents($f[1].'/src/Project/OfficeNativeProjectAccess.php');
$assert(str_contains($adapter,'function canRead(') && str_contains($adapter,'function canManage('),'Server ACL method lost');
$assert(str_starts_with(file_get_contents(dirname($f[2]).'/.htaccess'),"AuthType Basic\nAuthName private-test\nRequire valid-user\n"),'HTTP auth changed');
$after=$tree($f[0]);$assert($updater->run('--apply')['status']==='INSTALLED','Repeat apply not idempotent');
$assert($tree($f[0])===$after,'Repeat apply changed source/state');
$assert($updater->run('--status')['status']==='INSTALLED','Status failed');
$assert($updater->run('--rollback')['status']==='ROLLED_BACK','Rollback failed');
foreach ($before as $path=>$hash) $assert(hash_file('sha256',$f[0].$path)===$hash,'Original not restored: '.$path);
foreach ($sources as $path=>$code) if (!isset($observed[$path])) $assert(!file_exists($f[1].'/'.$path),'Added runtime not rolled back');

// Negative cases must fail before any deployment/state write.
foreach (['source','drift','protected','symlink','production','active','session','router','apache'] as $case) {
  $f=$fixture();$code=$sources;
  switch ($case) {
    case 'source': $code['src/Inzet/OnSiteActivationService.php']='<?php broken !';$reason='syntax';break;
    case 'drift': file_put_contents($f[1].'/src/Inzet/OnSiteDeviceTokenRepository.php',"<?php\n// newer work\n");$reason='unreviewed_server_code';break;
    case 'protected': file_put_contents($f[1].'/src/Project/OfficeNativeProjectAccess.php','<?php // later edit');$reason='preserved_file_changed';break;
    case 'symlink': unlink($f[1].'/src/Inzet/OnSiteEventInbox.php');symlink($f[1].'/src/Inzet/OnSiteHoursReview.php',$f[1].'/src/Inzet/OnSiteEventInbox.php');$reason='unsafe_path';break;
    case 'production': $f[4]['OFFICE_BASE_PATH']='/office';file_put_contents($f[3],'<?php return '.var_export($f[4],true).';');$reason='office_test_isolation_failed';break;
    case 'active': $f[4]['OFFICE_ONSITE_ADMIN_ISSUE_ENABLED']='enabled';file_put_contents($f[3],'<?php return '.var_export($f[4],true).';');$reason='consumer_gate_must_remain_closed';break;
    case 'session': unlink($f[1].'/src/Intake/OfficeMount.php');$reason='session_dependency_missing';break;
    case 'router': file_put_contents($f[2],'<?php // unsupported router');$reason='router_anchor_missing';break;
    case 'apache': file_put_contents(dirname($f[2]).'/.htaccess',"Require valid-user\n");$reason='apache_anchor_missing';break;
  }
  $before=$tree($f[0]);
  if ($case==='source') {
    try {$run($f,$code)->run('--apply');throw new RuntimeException('Invalid source accepted');} catch (ParseError) {}
  } else $blocked(static fn()=>$run($f,$code)->run('--apply'),$reason);
  $assert($before===$tree($f[0]),'Blocked update mutated files: '.$case);
}
$f=$fixture();$updater=$run($f,$sources);$updater->run('--apply');
file_put_contents($f[1].'/src/Inzet/OnSiteEventInbox.php','<?php // newer server edit');
$before=$tree($f[0]);$blocked(static fn()=>$updater->run('--rollback'),'deployed_code_changed');
$assert($before===$tree($f[0]),'Rollback overwrote newer work');
echo "Guarded runtime update: complete deployment, repeat, rollback, preserved auth/ACL/planning, and mutation-free negative cases OK\n";

// An interrupted journal accepts only known old/new files for rollback.
$f=$fixture();$updater=$run($f,$sources);$updater->run('--apply');
$state=glob($f[0].'/onsite-runtime-update-*/state.json')[0];
$journal=json_decode(file_get_contents($state),true,32,JSON_THROW_ON_ERROR);
$journal['state']='preparing';
file_put_contents($state,json_encode($journal,JSON_THROW_ON_ERROR));
foreach ($sources as $path=>$code) {
  if (isset($observed[$path])) file_put_contents($f[1].'/'.$path,$observed[$path]);
  else unlink($f[1].'/'.$path);
  break;
}
$blocked(static fn()=>$updater->run('--apply'),'interrupted_update_requires_rollback');
$assert($updater->run('--rollback')['status']==='ROLLED_BACK','Interrupted update rollback failed');

foreach (['config','backup','lock'] as $case) {
  $f=$fixture();$updater=$run($f,$sources);$updater->run('--apply');
  $state=glob($f[0].'/onsite-runtime-update-*/state.json')[0];$dir=dirname($state);
  if ($case==='config') {file_put_contents($f[3],"\n// later config edit\n",FILE_APPEND);$reason='update_journal_or_config_changed';}
  if ($case==='backup') {file_put_contents(glob($dir.'/*.before')[0],'changed');$reason='backup_changed';}
  if ($case==='lock') {$handle=fopen($dir.'/lock','c');flock($handle,LOCK_EX);$reason='update_lock_busy';}
  $before=$tree($f[0]);
  $blocked(static fn()=>$updater->run('--rollback'),$reason);
  $assert($before===$tree($f[0]),'Blocked rollback mutated source/state');
  if ($case==='lock') {flock($handle,LOCK_UN);fclose($handle);}
}
echo "Interrupted updates, snapshot/config drift and deployment lock refusal OK\n";

$f=$fixture();
$explicit="if (\$path === '/office-test/api/onsite/v1/activate') {\n  // existing dispatcher\n  require \$private . '/public/onsite-activate.php';\n  exit;\n}\n";
file_put_contents($f[2],$explicit,FILE_APPEND);
$updater=$run($f,$sources);$assert($updater->run('--apply')['status']==='INSTALLED','Existing explicit API dispatcher rejected');
$assert(substr_count(file_get_contents($f[2]),'/office-test/api/onsite/v1/activate')===1,'Existing API route duplicated');
$updater->run('--rollback');
$f=$fixture();file_put_contents($f[2],"// /office-test/api/onsite/v1/activate has unknown routing\n",FILE_APPEND);
$before=$tree($f[0]);$blocked(static fn()=>$run($f,$sources)->run('--apply'),'existing_route_requires_review');
$assert($before===$tree($f[0]),'Unrecognized API route overwritten');
echo "All five routes and Apache forwarding prepared; existing explicit API handlers preserved\n";
