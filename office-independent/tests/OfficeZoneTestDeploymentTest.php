<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/deployment/hostinger/OfficeZoneTestDeployment.php';
use Brebo\Office\Deployment\OfficeZoneTestDeployment;

$fixture=function():array {
  $top=sys_get_temp_dir().'/office-zone-deploy-'.bin2hex(random_bytes(8));
  $base=$top.'/test';$root=$base.'/release/private';$router=$top.'/www/office-test/index.php';$config=$base.'/config/config.php';
  foreach ([$root.'/src/Project',$root.'/public',dirname($router),dirname($config)] as $dir) mkdir($dir,0700,true);
  $shared=$root.'/src/Project/OfficeNativeProjectAccess.php';
  file_put_contents($shared,"<?php\n// preserved canRead and canManage\n");
  $routerSource="<?php\n".'// Do not match a commented $routes = ['."\n".'$root = '.var_export($root,true).";\n".'$routes = ['."\n '/office-test' => 'office-home.php',\n];\n";
  file_put_contents($router,$routerSource);
  $c=['OFFICE_BASE_PATH'=>'/office-test','OFFICE_PRIVATE_ROOT'=>$root,'UNCHANGED_SECRET'=>'do-not-print-fixture-secret',
    'OFFICE_CRM_SESSION_MODE'=>'enabled','OFFICE_NATIVE_PROJECTS_ENABLED'=>'enabled','OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED'=>'enabled'];
  $configSource="<?php\nreturn ".var_export($c,true).";\n";file_put_contents($config,$configSource);
  $files=['public/office-onsite-project-map.php'=>"<?php\n// map fixture\n",'public/office-onsite-zone-create.php'=>"<?php\n// post fixture\n"];
  $protected=['src/Project/OfficeNativeProjectAccess.php'=>hash_file('sha256',$shared)];
  return compact('top','base','root','router','config','routerSource','configSource','files','protected','shared');
};
$make=static fn(array $f,?Closure $gate=null)=>new OfficeZoneTestDeployment($f['base'],$f['root'],$f['router'],$f['config'],
  hash('sha256',$f['routerSource']),$f['protected'],$f['files'],$gate ?? static function(array $c):void {});
$deny=static function(Closure $call,string $message):void {
  try {$call();} catch (RuntimeException $e) {if ($e->getMessage()===$message)return;throw $e;}
  throw new RuntimeException('Expected block '.$message);
};
$f=$fixture();$d=$make($f);
if ($d->run('--check')['status']!=='READY_TO_INSTALL' || is_file($f['root'].'/public/office-onsite-project-map.php')
  || file_get_contents($f['router'])!==$f['routerSource']) throw new RuntimeException('Check mutated release');
$status=$d->run('--install');
if ($status['status']!=='INSTALLED' || $status['zone_write_enabled'] || file_get_contents($f['config'])!==$f['configSource']
  || !str_contains(file_get_contents($f['router']),"'/office-test/inzet/zone/aanmaken'")
  || hash_file('sha256',$f['shared'])!==$f['protected']['src/Project/OfficeNativeProjectAccess.php']) throw new RuntimeException('Isolated install contract failed');
if ($d->run('--install')!==$status) throw new RuntimeException('Install retry not stable');
$enabled=$d->run('--enable');
if (!$enabled['zone_write_enabled'] || !$enabled['zone_schema_verified'] || !$enabled['zone_secret_configured']) throw new RuntimeException('Enable failed');
$c=require $f['config'];
if ($c['UNCHANGED_SECRET']!=='do-not-print-fixture-secret' || strlen($c['OFFICE_TEST_ZONE_CSRF_SECRET'])<32
  || (fileperms($f['config'])&0777)!==0600 || str_contains(json_encode($enabled),$c['OFFICE_TEST_ZONE_CSRF_SECRET'])) {
  throw new RuntimeException('Config preservation or secret privacy failed');
}
if ($d->run('--enable')!==$enabled) throw new RuntimeException('Enable retry changed configuration');
$rolled=$d->run('--rollback');
if ($rolled['status']!=='ROLLED_BACK' || file_get_contents($f['router'])!==$f['routerSource']
  || file_get_contents($f['config'])!==$f['configSource']
  || !is_file($f['root'].'/public/office-onsite-project-map.php')) throw new RuntimeException('Rollback lost release/config/retained code');
$g=$fixture();$d=$make($g,static function(array $c):void {throw new RuntimeException('schema_block');});
$deny(fn()=>$d->run('--install'),'schema_block');
if (file_get_contents($g['router'])!==$g['routerSource'] || is_file($g['root'].'/public/office-onsite-project-map.php')) throw new RuntimeException('Schema failure mutated release');
$g=$fixture();file_put_contents($g['router'],"\n// another edit",FILE_APPEND);$d=$make($g);
$deny(fn()=>$d->run('--install'),'router_changed_since_inspection');
$g=$fixture();file_put_contents($g['shared'],"\n// another edit",FILE_APPEND);$d=$make($g);
$deny(fn()=>$d->run('--install'),'shared_file_changed:src/Project/OfficeNativeProjectAccess.php');
$g=$fixture();file_put_contents($g['root'].'/public/office-onsite-project-map.php','existing');$d=$make($g);
$deny(fn()=>$d->run('--install'),'target_already_exists:public/office-onsite-project-map.php');
$g=$fixture();$d=$make($g);$d->run('--install');file_put_contents($g['config'],"\n// later edit",FILE_APPEND);
$deny(fn()=>$d->run('--enable'),'installed_config_changed');
$deny(fn()=>$d->run('--rollback'),'runtime_changed_rollback_requires_review');
$g=$fixture();mkdir($g['root'].'/src/Inzet',0700);symlink($g['top'].'/www',$g['root'].'/src/Inzet/link');
$g['files']=['src/Inzet/link/escape.php'=>'<?php'];$d=$make($g);
$deny(fn()=>$d->run('--install'),'unsafe_payload_path');
$g=$fixture();$g['files']=['public/office-onsite-project-map.php'=>'<?php syntax error here'];$d=$make($g);
$parseBlocked=false;try {$d->run('--install');}catch(ParseError){$parseBlocked=true;}
if (!$parseBlocked || file_get_contents($g['router'])!==$g['routerSource']) throw new RuntimeException('Invalid PHP reached publication');
echo "Isolated zone deployment, activation, rollback and change guards OK\n";

