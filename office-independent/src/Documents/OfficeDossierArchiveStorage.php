<?php
declare(strict_types=1);
namespace Brebo\Office\Documents;

require_once __DIR__.'/OfficePrivateFileStorage.php';

/** Dedicated private archive root, separate from live document originals. */
final class OfficeDossierArchiveStorage
{
    private readonly OfficePrivateFileStorage $storage;

    public function __construct(string $archiveRoot)
    {
        $this->storage = new OfficePrivateFileStorage($archiveRoot);
    }

    /** Only authorized workers may publish verified encrypted staging files. */
    public function publish(string $encryptedPath,string $expectedHash): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$expectedHash) || !is_file($encryptedPath) || is_link($encryptedPath) || !hash_equals($expectedHash,(string)hash_file('sha256',$encryptedPath))) {
            throw new \RuntimeException('Invalid encrypted archive');
        }
        $stored=$this->storage->put($encryptedPath);
        if (!hash_equals($expectedHash,$stored['sha256'])) throw new \RuntimeException('Archive publication mismatch');
        $this->storage->authorizedPath($stored['storage_key']);
        return $stored;
    }

    public function authorizedPath(string $storageKey,string $expectedHash): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$expectedHash) || $storageKey!=='originals/'.substr($expectedHash,0,2).'/'.$expectedHash) throw new \RuntimeException('Archive identity mismatch');
        return $this->storage->authorizedPath($storageKey);
    }
}
