<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Documents/OfficePrivateFileStorage.php';
$root=sys_get_temp_dir().'/material-storage-'.bin2hex(random_bytes(8));mkdir($root,0700);mkdir($root.'/private',0700);
$_SERVER['DOCUMENT_ROOT']='';
try{
 file_put_contents($root.'/source.txt','Original content');
 $storage=new \Brebo\Office\Documents\OfficePrivateFileStorage($root.'/private');
 $first=$storage->put($root.'/source.txt');$second=$storage->put($root.'/source.txt');
 if($first!==$second||file_get_contents($storage->authorizedPath($first['storage_key']))!=='Original content')throw new RuntimeException('Immutable original roundtrip or deduplication failed');
 $path=$storage->authorizedPath($first['storage_key']);file_put_contents($path,'Tampered');
 $blocked=false;try{$storage->put($root.'/source.txt');}catch(RuntimeException){$blocked=true;}
 if(!$blocked)throw new RuntimeException('Existing originals must not be overwritten');
 echo 'MATERIAL_PORTABLE_STORAGE=PASS '.(function_exists('link')?'hardlink':'locked-rename')."\n";
}finally{
 $remove=static function(string $path)use(&$remove):void{foreach(scandir($path)?:[]as$name){if($name==='.'||$name==='..')continue;$child=$path.'/'.$name;if(is_dir($child)&&!is_link($child))$remove($child);else unlink($child);}rmdir($path);};$remove($root);
}
