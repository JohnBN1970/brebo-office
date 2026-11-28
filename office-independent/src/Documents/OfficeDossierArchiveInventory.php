<?php
declare(strict_types=1);
namespace Brebo\Office\Documents;

use PDO;
require_once __DIR__.'/OfficePrivateFileStorage.php';
require_once __DIR__.'/OfficeDossierArchiveManifest.php';

final class OfficeDossierArchiveInventory
{
    public function __construct(private readonly PDO $db, private readonly OfficePrivateFileStorage $storage) {}

    /** Internal worker only. Contexts must be resolved and authorized server-side.
     * No source removal is authorized by this inventory.
     */
    public function collect(array $contexts): array
    {
        if (!$contexts) throw new \InvalidArgumentException('Missing dossier contexts');
        $query = $this->db->prepare('SELECT DISTINCT d.id AS document_id, o.id AS original_id, o.storage_provider, o.storage_key, o.sha256, o.byte_size FROM office_document_contexts c JOIN office_documents d ON d.id=c.document_id JOIN office_document_originals o ON o.id=d.original_id WHERE c.context_type=? AND c.context_id=?');
        $entries = [];
        foreach ($contexts as $context) {
            $type = $context['type'] ?? null;
            $id = $context['id'] ?? null;
            if (!in_array($type, ['project','organization','contact'], true) || !is_string($id) || $id === '' || strlen($id)>100) throw new \InvalidArgumentException('Invalid dossier context');
            $query->execute([$type,$id]);
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['storage_provider'] !== 'office_private') throw new \RuntimeException('Unsupported original storage');
                $path = $this->storage->authorizedPath($row['storage_key']);
                if (!hash_equals($row['sha256'], (string)hash_file('sha256',$path)) || filesize($path)!==(int)$row['byte_size']) throw new \RuntimeException('Original metadata mismatch');
                $documentId = (int)$row['document_id'];
                $entries[$documentId] = ['document_id'=>$documentId,'storage_key'=>$row['storage_key'],'sha256'=>$row['sha256'],'size_bytes'=>(int)$row['byte_size']];
            }
        }
        return ['manifest'=>OfficeDossierArchiveManifest::build(array_values($entries)), 'document_count'=>count($entries)];
    }
}
