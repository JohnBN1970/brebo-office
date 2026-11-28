<?php
declare(strict_types=1);
namespace Brebo\Office\Documents;

use PDO;
require_once __DIR__.'/OfficeDossierArchiveStorage.php';

final class OfficeDossierArchiveRepository
{
    public function __construct(private readonly PDO $db, private readonly OfficeDossierArchiveStorage $storage) {}

    /** Record durable storage while still verifying; does not authorize archival or deletion.
     * Caller must be an authorized worker. archive_sha256 stores the encrypted object digest.
     */
    public function recordPublication(int $id,int $revision,string $actor,string $path,string $encryptedHash,string $manifestHash): int
    {
        if ($id<1 || $revision<1 || trim($actor)==='' || strlen($actor)>128 || !preg_match('/^[a-f0-9]{64}$/D',$manifestHash)) throw new \InvalidArgumentException('Invalid publication');
        $stored=$this->storage->publish($path,$encryptedHash);
        $this->db->beginTransaction();
        try {
            $now=gmdate('Y-m-d H:i:s');
            $update=$this->db->prepare("UPDATE office_dossier_archives SET storage_key=?, manifest_sha256=?, archive_sha256=?, revision=revision+1, updated_at=? WHERE id=? AND revision=? AND status='verifying' AND storage_key IS NULL");
            $update->execute([$stored['storage_key'],$manifestHash,$encryptedHash,$now,$id,$revision]);
            if ($update->rowCount()!==1) throw new \RuntimeException('Archive publication conflict');
            $event=$this->db->prepare('INSERT INTO office_dossier_archive_events (archive_id,revision,actor_id,from_status,to_status,reason,created_at) VALUES (?,?,?,?,?,?,?)');
            $event->execute([$id,$revision+1,$actor,'verifying','verifying','Encrypted object published; restore verification pending',$now]);
            $this->db->commit();
            return $revision+1;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            // Immutable object may remain unreferenced; never delete it here.
            throw $e;
        }
    }
}
