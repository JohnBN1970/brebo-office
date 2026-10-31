<?php
declare(strict_types=1);
namespace Brebo\Office\Communication;

/** One-shot sync. Cursor is returned only after successful persistence. */
final class OfficeInboxSyncService
{
    public function __construct(
        private readonly OfficeImapReadOnlySource $source,
        private readonly OfficeInboxImportBatch $importer,
        private readonly ?OfficeImapCursorRepository $cursors = null
    ) {}

    public function sync(int $mailboxId, int $afterUid, int $limit = 25, ?int $expectedUidValidity = null): array
    {
        if ($mailboxId < 1 || $afterUid < 0 || $limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Invalid sync request.');
        }
        $batch = $this->source->fetchAfter($afterUid, $limit);
        if ($expectedUidValidity !== null && $expectedUidValidity !== $batch['uidvalidity']) {
            throw new \RuntimeException('IMAP UIDVALIDITY changed; cursor reset requires explicit review.');
        }
        $ids = $this->importer->import($mailboxId, 'INBOX', $batch);
        $lastUid = $afterUid;
        foreach ($batch['messages'] as $message) {
            $lastUid = max($lastUid, $message['uid']);
        }
        return ['last_uid' => $lastUid, 'uidvalidity' => $batch['uidvalidity'], 'ids' => $ids];
    }

    /**
     * Persist the cursor only after the entire batch has been imported.
     * A failed import leaves the cursor unchanged so the batch can be retried.
     * Run one sync worker per mailbox; cursor CAS detects competing writers.
     */
    public function syncDurable(int $mailboxId, int $limit = 25): array
    {
        if ($this->cursors === null) {
            throw new \RuntimeException('Durable IMAP cursor repository not configured.');
        }
        $before = $this->cursors->get($mailboxId, 'INBOX');
        $result = $this->sync(
            $mailboxId,
            $before['last_uid'] ?? 0,
            $limit,
            $before['uidvalidity'] ?? null
        );
        $this->cursors->advance(
            $mailboxId, 'INBOX', $result['uidvalidity'], $result['last_uid'], $before
        );
        return $result;
    }
}
