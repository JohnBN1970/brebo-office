<?php
declare(strict_types=1);
use Brebo\Office\Documents\OfficePrivateFileStorage;
use Brebo\Office\Documents\OfficeDossierArchiveManifest;
use Brebo\Office\Documents\OfficeDossierArchiveCompression;
use Brebo\Office\Documents\OfficeDossierArchiveCipher;
use Brebo\Office\Documents\OfficeDossierArchiveRestore;
require_once __DIR__.'/../src/Documents/OfficeDossierArchiveCompression.php';
require_once __DIR__.'/../src/Documents/OfficeDossierArchiveCipher.php';
require_once __DIR__.'/../src/Documents/OfficeDossierArchiveRestore.php';

$root=sys_get_temp_dir().'/office-archive-test-'.bin2hex(random_bytes(12));
mkdir($root,0700);
$reject=static function(callable $fn):void {
    try {$fn();} catch (RuntimeException|InvalidArgumentException $e) {return;}
    throw new RuntimeException('Expected rejection');
};
try {
    $storage=new OfficePrivateFileStorage($root);
    $source=$root.'/source';
    $payload=str_repeat('Office dossier test ',10000);
    file_put_contents($source,$payload);
    $stored=$storage->put($source);
    $manifest=OfficeDossierArchiveManifest::build([['document_id'=>42,'storage_key'=>$stored['storage_key'],'sha256'=>$stored['sha256'],'size_bytes'=>$stored['size']]]);
    $zip=(new OfficeDossierArchiveCompression($storage))->create($manifest,$root);
    $key=random_bytes(32);
    $encrypted=OfficeDossierArchiveCipher::transform($zip['staged_path'],$root,$key,false,1000000);
    $reject(fn()=>OfficeDossierArchiveCipher::transform($encrypted,$root,random_bytes(32),true,1000000));
    $decoded=OfficeDossierArchiveCipher::transform($encrypted,$root,$key,true,1000000);
    $restore=new OfficeDossierArchiveRestore($storage);
    $reject(fn()=>$restore->restore($decoded,$manifest,$zip['archive_sha256'],$root,1));
    $reject(fn()=>$restore->restore($decoded,$manifest,str_repeat('0',64),$root,1000000));
    unlink($storage->authorizedPath($stored['storage_key']));
    $result=$restore->restore($decoded,$manifest,$zip['archive_sha256'],$root,1000000);
    if ($result!==[42=>$stored['storage_key']] || file_get_contents($storage->authorizedPath($stored['storage_key']))!==$payload) throw new RuntimeException('Roundtrip mismatch');
    echo "Archive roundtrip OK\n";
} finally {
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $file) {if($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname());}
    rmdir($root);
}
