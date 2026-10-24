<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/deployment/hostinger/OfficeZoneEditTestDeployment.php';
use Brebo\Office\Deployment\OfficeZoneEditTestDeployment;
$base=sys_get_temp_dir().'/office-zone-edit-'.bin2hex(random_bytes(8));
$root=$base.'/private';$router=$base.'/router.php';$config=$base.'/config.php';
mkdir($root.'/public',0700,true);mkdir($root.'/src/Buildings',0700,true);mkdir($root.'/src/Inzet',0700,true);
$old=[];$files=[];
foreach ([...OfficeZoneEditTestDeployment::REPLACE,...OfficeZoneEditTestDeployment::ADD] as $path) {
  $files[$path]="<?php\n// new ".$path."\n";
  if (in_array($path,OfficeZoneEditTestDeployment::REPLACE,true)) {
    $old[$path]="<?php\n// old ".$path."\n";
    file_put_contents($root.'/'.$path,$old[$path]);
  }
}
$routerOriginal="<?php\n\$routes = [\n  '/office-test/inzet/zone/aanmaken' => 'office-onsite-zone-create.php',\n];\n";
file_put_contents($router,$routerOriginal);
file_put_contents($base.'/.htaccess',"RewriteEngine On\nRewriteRule ^inzet/zone/bewerken/?$ index.php [END,QSA]\n");
file_put_contents($config,"<?php\nreturn ".var_export([
 'OFFICE_BASE_PATH'=>'/office-test','OFFICE_PRIVATE_ROOT'=>$root,
 'OFFICE_PUBLIC_HOST'=>'brebobv.nl','OFFICE_INTAKE_DB_USER'=>'u213420663_office_test',
 'OFFICE_INTAKE_PDO_DSN'=>'mysql:host=localhost;dbname=u213420663_office_test;charset=utf8mb4',
 'OFFICE_TEST_ZONE_WRITE_ENABLED'=>'enabled','OFFICE_TEST_BUILDING_ZONE_SCHEMA_VERIFIED'=>'enabled'
],true).";\n");
mkdir($base.'/zone-deploy-original',0700);
file_put_contents($base.'/zone-deploy-original/state.json',json_encode(['state'=>'enabled'],JSON_THROW_ON_ERROR));
$installer=new OfficeZoneEditTestDeployment($base,$root,$router,$config,$files);
if (($installer->run('--check')['status']??'')!=='READY_TO_APPLY') throw new RuntimeException('zone_edit_preflight_failed');
foreach (OfficeZoneEditTestDeployment::ADD as $path) if (file_exists($root.'/'.$path)) throw new RuntimeException('check_created_file');
if (($installer->run('--apply')['status']??'')!=='APPLIED') throw new RuntimeException('zone_edit_apply_failed');
foreach ($files as $path=>$source) if (file_get_contents($root.'/'.$path)!==$source) throw new RuntimeException('zone_edit_file_not_applied');
if (!str_contains(file_get_contents($router),"'/office-test/inzet/zone/bewerken'")) throw new RuntimeException('zone_edit_route_missing');
if (($installer->run('--status')['status']??'')!=='APPLIED') throw new RuntimeException('zone_edit_status_failed');
if (($installer->run('--rollback')['status']??'')!=='ROLLED_BACK') throw new RuntimeException('zone_edit_rollback_failed');
if (file_get_contents($router)!==$routerOriginal) throw new RuntimeException('zone_edit_router_not_restored');
foreach ($old as $path=>$source) if (file_get_contents($root.'/'.$path)!==$source) throw new RuntimeException('zone_edit_original_not_restored');
foreach (OfficeZoneEditTestDeployment::ADD as $path) if (file_exists($root.'/'.$path)) throw new RuntimeException('zone_edit_new_file_not_removed');
echo "Office-test zone edit installer and rollback passed\n";
