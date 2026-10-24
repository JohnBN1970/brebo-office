<?php
declare(strict_types=1);
require __DIR__.'/fixtures/operational-review.php';
use Brebo\Office\Inzet\OfficeInzetOperationalReviewConfig as Config;
$top=sys_get_temp_dir().'/operational-config-'.bin2hex(random_bytes(8));mkdir($top,0700);mkdir($top.'/private',0700);mkdir($top.'/web',0700);
try {
  $file=$top.'/private/operations.sqlite';$f=operationalFixture(null,new PDO('sqlite:'.$file));unset($f);
  $identities=['version'=>1,'mappings'=>[
    ['source_system'=>'office_operator','source_person_id'=>'01','office_person_id'=>1101],
    ['source_system'=>'office_operator','source_person_id'=>'1-other','office_person_id'=>2101],
  ]];
  $map=$top.'/private/map.json';file_put_contents($map,json_encode($identities,JSON_THROW_ON_ERROR));
  $hash=hash_file('sha256',$file);$before=scandir($top.'/private');
  $db=Config::open($file,$top.'/private',$top.'/web');
  operationalAssert((int)$db->query('SELECT COUNT(*) FROM inzet_registration')->fetchColumn()===4,'Existing operational schema accepted');
  operationalDenied(fn()=>$db->exec("UPDATE inzet_registration SET state='approved'"),PDOException::class);unset($db);
  operationalAssert(hash_file('sha256',$file)===$hash && scandir($top.'/private')===$before,'Read-only check has no file or sidecar writes');
  $mapping=Config::identityMap($map,$top.'/private',$top.'/web');
  operationalAssert($mapping['01']===1101 && $mapping['1-other']===2101 && !isset($mapping['1']),'Configured actor strings remain exact');
  operationalDenied(fn()=>Config::open($top.'/private/missing.sqlite',$top.'/private',$top.'/web'),RuntimeException::class);
  operationalAssert(!file_exists($top.'/private/missing.sqlite'),'Missing store is never created');
  copy($file,$top.'/web/operations.sqlite');
  operationalDenied(fn()=>Config::open($top.'/web/operations.sqlite',$top.'/web',$top.'/web'),RuntimeException::class);
  operationalDenied(fn()=>Config::open($file,$top.'/private',$top.'/web',true,[$file]),RuntimeException::class);
  foreach([
    'ALTER TABLE inzet_operational_audit ADD COLUMN unexpected TEXT',
    'CREATE TABLE unknown_records(id INTEGER)',
    'CREATE TRIGGER unexpected AFTER UPDATE ON inzet_registration BEGIN SELECT 1; END',
    'PRAGMA journal_mode=WAL',
  ] as $i=>$sql){
    $target=$top.'/private/bad-'.$i.'.sqlite';copy($file,$target);$edit=new PDO('sqlite:'.$target);$edit->exec($sql);unset($edit);
    $badHash=hash_file('sha256',$target);$filesBefore=scandir($top.'/private');
    operationalDenied(fn()=>Config::open($target,$top.'/private',$top.'/web'),RuntimeException::class);
    operationalAssert(hash_file('sha256',$target)===$badHash && scandir($top.'/private')===$filesBefore,'Invalid schema/WAL check makes no changes');
  }
  foreach([
    ['version'=>1,'mappings'=>[]],
    ['version'=>1,'mappings'=>[$identities['mappings'][0],$identities['mappings'][0]]],
    ['version'=>1,'mappings'=>[$identities['mappings'][0],['source_system'=>'office_operator','source_person_id'=>'other','office_person_id'=>1101]]],
    ['version'=>1,'mappings'=>[['source_system'=>'excel_label','source_person_id'=>'worker','office_person_id'=>1101]]],
  ] as $invalid){
    file_put_contents($map,json_encode($invalid,JSON_THROW_ON_ERROR));
    operationalDenied(fn()=>Config::identityMap($map,$top.'/private',$top.'/web'));
  }
  operationalAssert(hash_file('sha256',$file)===$hash,'Original store unchanged by all checks');
  echo "Operational configuration: exact identities, private boundaries, existing files, read-only schema, alias/WAL and sidecar protection OK\n";
} finally {
  unset($db,$edit,$f);
  $real=realpath($top);$tmp=realpath(sys_get_temp_dir());
  if($real!==false && $tmp!==false && str_starts_with(str_replace('\\','/',$real),rtrim(str_replace('\\','/',$tmp),'/').'/operational-config-')){
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $entry){if($entry->isDir()&&!$entry->isLink())rmdir($entry->getPathname());else unlink($entry->getPathname());}rmdir($real);
  }
}
