<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/deployment/hostinger/OfficeZoneTestUpdate.php';
use Brebo\Office\Deployment\OfficeZoneTestUpdate;

$base=sys_get_temp_dir().'/office-zone-update-'.bin2hex(random_bytes(8));
$root=$base.'/private';
$router=$base.'/router.php';
$config=$base.'/config.php';
$paths=OfficeZoneTestUpdate::TARGETS;
mkdir($root.'/public',0700,true);
mkdir($root.'/src/Buildings',0700,true);
$old=[];$new=[];$hashes=[];
foreach ($paths as $path) {
  $old[$path]="<?php\n// original ".basename($path)."\n";
  $new[$path]="<?php\n// updated ".basename($path)."\n";
  file_put_contents($root.'/'.$path,$old[$path]);
  $hashes[$path]=hash('sha256',$old[$path]);
}
file_put_contents($router,"<?php\n// router\n");
file_put_contents($config,"<?php\nreturn [];\n");
$stateDir=$base.'/zone-deploy-original';
mkdir($stateDir,0700);
file_put_contents($stateDir.'/state.json',json_encode([
  'state'=>'enabled','router_installed'=>hash_file('sha256',$router),
  'config_enabled'=>hash_file('sha256',$config),'files'=>$hashes
],JSON_THROW_ON_ERROR));
$updater=new OfficeZoneTestUpdate($base,$root,$router,$config,$new);
$check=$updater->run('--check');
if (($check['status']??'')!=='READY_TO_UPDATE') throw new RuntimeException('preflight failed');
foreach ($paths as $path) if (file_get_contents($root.'/'.$path)!==$old[$path]) throw new RuntimeException('check mutated source');
$applied=$updater->run('--apply');
if (($applied['status']??'')!=='APPLIED') throw new RuntimeException('apply failed');
foreach ($paths as $path) if (file_get_contents($root.'/'.$path)!==$new[$path]) throw new RuntimeException('update not applied');
if (($updater->run('--status')['status']??'')!=='APPLIED') throw new RuntimeException('status failed');
file_put_contents($root.'/'.$paths[0],"<?php\n// external edit\n");
$blocked=false;
try {$updater->run('--rollback');} catch (RuntimeException $e) {$blocked=true;}
if (!$blocked) throw new RuntimeException('unsafe rollback accepted');
file_put_contents($root.'/'.$paths[0],$new[$paths[0]]);
if (($updater->run('--rollback')['status']??'')!=='ROLLED_BACK') throw new RuntimeException('rollback failed');
foreach ($paths as $path) if (file_get_contents($root.'/'.$path)!==$old[$path]) throw new RuntimeException('rollback did not restore');
if (file_get_contents($router)!=="<?php\n// router\n" || file_get_contents($config)!=="<?php\nreturn [];\n") {
  throw new RuntimeException('router or config modified');
}
foreach (glob($base.'/zone-update-*/*')?:[] as $file) unlink($file);
foreach (glob($base.'/zone-update-*')?:[] as $dir) rmdir($dir);
unlink($stateDir.'/state.json');rmdir($stateDir);
foreach ($paths as $path) unlink($root.'/'.$path);
rmdir($root.'/src/Buildings');rmdir($root.'/src');rmdir($root.'/public');rmdir($root);
unlink($router);unlink($config);rmdir($base);
echo "Office zone guarded update checks passed\n";
