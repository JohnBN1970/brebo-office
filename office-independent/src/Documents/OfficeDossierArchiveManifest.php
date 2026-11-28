<?php
declare(strict_types=1);
namespace Brebo\Office\Documents;

final class OfficeDossierArchiveManifest
{
    /** Entries come from an authorized inventory, not from archive-supplied paths. */
    public static function build(array $entries): string
    {
        $files = [];
        foreach ($entries as $entry) {
            $id = $entry['document_id'] ?? null;
            $hash = $entry['sha256'] ?? null;
            $size = $entry['size_bytes'] ?? null;
            $key = $entry['storage_key'] ?? null;
            if (!is_int($id) || $id < 1 || isset($files[$id]) ||
                !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash) ||
                !is_int($size) || $size < 0 || !is_string($key) || $key === '' ||
                strlen($key) > 1024 || preg_match('/[[:cntrl:]]/', $key)) {
                throw new \InvalidArgumentException('Invalid archive inventory entry');
            }
            $files[$id] = [
                'document_id' => $id,
                'storage_key' => $key,
                'archive_entry' => 'documents/'.$id,
                'size_bytes' => $size,
                'sha256' => $hash,
            ];
        }
        ksort($files, SORT_NUMERIC);
        return json_encode(['version'=>1,'documents'=>array_values($files)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
