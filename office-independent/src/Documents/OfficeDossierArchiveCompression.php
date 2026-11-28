<?php
declare(strict_types=1);
namespace Brebo\Office\Documents;

require_once __DIR__.'/OfficePrivateFileStorage.php';
require_once __DIR__.'/OfficeDossierArchiveManifest.php';

/** Internal worker primitive. Output remains staged; never deletes originals. */
final class OfficeDossierArchiveCompression
{
    public function __construct(private readonly OfficePrivateFileStorage $storage) {}

    public function create(string $manifest, string $privateStagingDirectory): array
    {
        $data = json_decode($manifest, true, 512, JSON_THROW_ON_ERROR);
        $entries = $data['documents'] ?? [];
        if (!$entries || OfficeDossierArchiveManifest::build($entries) !== $manifest) {
            throw new \InvalidArgumentException('Invalid or empty archive manifest');
        }
        $dir = realpath($privateStagingDirectory);
        if ($dir === false || !is_dir($dir) || is_link($privateStagingDirectory) || (fileperms($dir)&0077)!==0) {
            throw new \RuntimeException('Private staging directory required');
        }
        $web = realpath($_SERVER['DOCUMENT_ROOT'] ?? '/nonexistent');
        if ($web !== false && ($dir === $web || str_starts_with($dir, rtrim($web,'/').'/'))) throw new \RuntimeException('Staging outside webroot required');
        $path = tempnam($dir, 'archive-');
        if ($path === false) throw new \RuntimeException('Cannot allocate archive');
        chmod($path,0600);
        $zip = new \ZipArchive();
        $opened = false;
        try {
            if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)!==true) throw new \RuntimeException('Cannot open ZIP');
            $opened = true;
            if (!$zip->addFromString('manifest.json',$manifest)) throw new \RuntimeException('Manifest write failed');
            foreach ($entries as $entry) {
                $source = $this->storage->authorizedPath($entry['storage_key']);
                if (filesize($source)!==$entry['size_bytes'] || !hash_equals($entry['sha256'],(string)hash_file('sha256',$source))) throw new \RuntimeException('Source changed');
                if (!$zip->addFile($source,$entry['archive_entry']) || !$zip->setCompressionName($entry['archive_entry'],\ZipArchive::CM_DEFLATE)) throw new \RuntimeException('Compression failed');
            }
            $closed = $zip->close();
            $opened = false;
            if (!$closed) throw new \RuntimeException('ZIP close failed');
            if ($zip->open($path,\ZipArchive::RDONLY | \ZipArchive::CHECKCONS)!==true) throw new \RuntimeException('ZIP verification failed');
            $opened = true;
            if ($zip->numFiles!==count($entries)+1 || $zip->getFromName('manifest.json')!==$manifest) throw new \RuntimeException('Archive manifest mismatch');
            foreach ($entries as $entry) {
                $stat = $zip->statName($entry['archive_entry']);
                if (!$stat || $stat['size']!==$entry['size_bytes']) throw new \RuntimeException('Archive size mismatch');
                $stream = $zip->getStream($entry['archive_entry']);
                if ($stream===false) throw new \RuntimeException('Archive entry unavailable');
                try {
                    $hash = hash_init('sha256');
                    $bytes = hash_update_stream($hash,$stream);
                    if ($bytes!==$entry['size_bytes'] || !hash_equals($entry['sha256'],hash_final($hash))) throw new \RuntimeException('Archive content mismatch');
                } finally { fclose($stream); }
            }
            $zip->close();
            $opened = false;
            $hash = hash_file('sha256',$path);
            if ($hash===false) throw new \RuntimeException('Archive hash failed');
            return ['staged_path'=>$path,'archive_sha256'=>$hash,'manifest_sha256'=>hash('sha256',$manifest)];
        } catch (\Throwable $e) {
            if ($opened) $zip->close();
            @unlink($path);
            throw $e;
        }
    }
}
