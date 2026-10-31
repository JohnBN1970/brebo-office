<?php
declare(strict_types=1);
namespace Brebo\Office\Communication;

/** One-shot sync. Cursor is returned only after successful persistence. */
final class OfficeInboxSyncService
{
    public function __construct(
        private readonly OfficeImapReadOnlySource $source,
        private readonly OfficeInboxImportBatch $importer
    ) {}

    public function sync(int $mailboxId, int $afterUid, int $limit = 25): array
    {
        if ($mailboxId < 1 || $afterUid < 0 || $limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Invalid sync request.');
        }
        $batch = $this->source->fetchAfter($afterUid, $limit);
        $ids = $this->importer->import($mailboxId, 'INBOX', $batch);
        $lastUid = $afterUid;
        foreach ($batch['messages'] as $message) {
            $lastUid = max($lastUid, $message['uid']);
        }
        return ['last_uid' => $lastUid, 'uidvalidity' => $batch['uidvalidity'], 'ids' => $ids];
    }
}
