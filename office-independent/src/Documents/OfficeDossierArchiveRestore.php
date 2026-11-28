<?php
declare(strict_types=1);
namespace Brebo\Office\Documents;

require_once __DIR__.'/OfficePrivateFileStorage.php';
require_once __DIR__.'/OfficeDossierArchiveManifest.php';

/** Internal authorized worker; does not publish active dossier status. */
final class OfficeDossierArchiveRestore
{
    public function __construct(private readonly OfficePrivateFileStorage $storage) {}

    public function restore(string $archive, string $manifest, string $expectedHash, string $staging, int $maxBytes): array
    {
        $data = json_decode($manifest,true,512,JSON_THROW_ON_ERROR);
        $entries = $data['documents'] ?? [];
        if (!$entries || OfficeDossierArchiveManifest::build($entries)!==$manifest || $maxBytes<1 ||
            !preg_match('/^[a-f0-9]{64}$/D',$expectedHash) || !is_file($archive) || is_link($archive) ||
            !hash_equals($expectedHash,(string)hash_file('sha256',$archive))) throw new \RuntimeException('Invalid archive');
        $dir = realpath($staging);
        if ($dir===false || !is_dir($dir) || is_link($staging) || (fileperms($dir)&0077)!==0) throw new \RuntimeException('Private staging required');
        $web = realpath($_SERVER['DOCUMENT_ROOT'] ?? '/nonexistent');
        if ($web!==false && ($dir===$web || str_starts_with($dir,rtrim($web,'/').'/'))) throw new \RuntimeException('Staging outside webroot required');
        $remaining = $maxBytes;
        foreach ($entries as $entry) {
            if ($entry['size_bytes']>$remaining || $entry['storage_key']!=='originals/'.substr($entry['sha256'],0,2).'/'.$entry['sha256']) throw new \RuntimeException('Restore limit or storage identity mismatch');
            $remaining -= $entry['size_bytes'];
        }
        $zip = new \ZipArchive();
        if ($zip->open($archive,\ZipArchive::RDONLY|\ZipArchive::CHECKCONS)!==true) throw new \RuntimeException('Invalid ZIP');
        $temporary = [];
        try {
            $stat = $zip->statName('manifest.json');
            if (!$stat || $stat['size']!==strlen($manifest) || $zip->numFiles!==count($entries)+1 || $zip->getFromName('manifest.json')!==$manifest) throw new \RuntimeException('Manifest mismatch');
            foreach ($entries as $entry) {
                $stat = $zip->statName($entry['archive_entry']);
                if (!$stat || $stat['size']!==$entry['size_bytes']) throw new \RuntimeException('Entry size mismatch');
                $path = tempnam($dir,'restore-');
                if ($path===false) throw new \RuntimeException('Staging failed');
                $temporary[$entry['document_id']]=$path;
                chmod($path,0600);
                $in=$zip->getStream($entry['archive_entry']);
                if ($in===false) throw new \RuntimeException('Entry unavailable');
                try {
                    $out=fopen($path,'wb');
                    if ($out===false) throw new \RuntimeException('Staging open failed');
                    try {
                        $left=$entry['size_bytes'];
                        while ($left>0) {
                            $chunk=fread($in,min(65536,$left));
                            if ($chunk===false || $chunk==='' || fwrite($out,$chunk)!==strlen($chunk)) throw new \RuntimeException('Restore copy failed');
                            $left-=strlen($chunk);
                        }
                        if (fread($in,1)!=='') throw new \RuntimeException('Entry exceeds manifest');
                    } finally { fclose($out); }
                } finally { fclose($in); }
                if (!hash_equals($entry['sha256'],(string)hash_file('sha256',$path))) throw new \RuntimeException('Restored hash mismatch');
            }
            $restored=[];
            foreach ($entries as $entry) {
                $stored=$this->storage->put($temporary[$entry['document_id']]);
                if ($stored['storage_key']!==$entry['storage_key'] || $stored['size']!==$entry['size_bytes']) throw new \RuntimeException('Storage verification failed');
                $restored[$entry['document_id']]=$stored['storage_key'];
            }
            return $restored;
        } finally {
            $zip->close();
            foreach ($temporary as $path) @unlink($path);
        }
    }
}
