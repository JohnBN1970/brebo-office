<?php
declare(strict_types=1);
namespace Brebo\Office\Communication;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Durable IMAP position, scoped to an Office mailbox and folder. */
final class OfficeImapCursorRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array{uidvalidity:int,last_uid:int}|null */
    public function get(int $mailboxId, string $folder = 'INBOX'): ?array
    {
        $this->validate($mailboxId, $folder);
        $stmt = $this->pdo->prepare('SELECT uidvalidity, last_uid FROM office_communication_cursors WHERE mailbox_id = :id AND folder = :folder');
        $stmt->execute([':id'=>$mailboxId, ':folder'=>$folder]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : ['uidvalidity'=>(int)$row['uidvalidity'], 'last_uid'=>(int)$row['last_uid']];
    }

    /**
     * Compare-and-set protects against stale workers and UIDVALIDITY changes.
     * A mismatch must be reviewed, not automatically reset.
     */
    public function advance(int $mailboxId, string $folder, int $uidValidity, int $lastUid, ?array $expected): void
    {
        $this->validate($mailboxId, $folder);
        if ($uidValidity < 1 || $lastUid < 0) throw new InvalidArgumentException('Invalid IMAP position.');
        $this->pdo->beginTransaction();
        try {
            $current = $this->get($mailboxId, $folder);
            if ($current !== $expected) throw new RuntimeException('IMAP cursor changed concurrently.');
            if ($current !== null && ($current['uidvalidity'] !== $uidValidity || $lastUid < $current['last_uid'])) {
                throw new RuntimeException('IMAP cursor rollback or UIDVALIDITY change requires review.');
            }
            if ($current === null) {
                $stmt = $this->pdo->prepare('INSERT INTO office_communication_cursors (mailbox_id, folder, uidvalidity, last_uid) VALUES (:id,:folder,:validity,:uid)');
            } else {
                $stmt = $this->pdo->prepare('UPDATE office_communication_cursors SET uidvalidity=:validity,last_uid=:uid WHERE mailbox_id=:id AND folder=:folder AND uidvalidity=:old_validity AND last_uid=:old_uid');
                $stmt->bindValue(':old_validity', $current['uidvalidity'], PDO::PARAM_INT);
                $stmt->bindValue(':old_uid', $current['last_uid'], PDO::PARAM_INT);
            }
            $stmt->bindValue(':id', $mailboxId, PDO::PARAM_INT);
            $stmt->bindValue(':folder', $folder);
            $stmt->bindValue(':validity', $uidValidity, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $lastUid, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() !== 1 && $current !== null && $lastUid !== $current['last_uid']) {
                throw new RuntimeException('IMAP cursor update failed.');
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function validate(int $mailboxId, string $folder): void
    {
        if ($mailboxId < 1 || $folder !== 'INBOX') throw new InvalidArgumentException('Invalid mailbox cursor scope.');
    }
}
