<?php
declare(strict_types=1);

namespace Brebo\Office\Communication;

use InvalidArgumentException;
use RuntimeException;

/**
 * Import one already-fetched IMAP batch into native Office.
 * Fail closed for MIME: raw multipart or encoded messages must not be
 * presented as decoded text or silently lose attachments.
 * No provider access, cursor mutation, approval or AI action here.
 */
final class OfficeInboxImportBatch
{
    public function __construct(
        private readonly OfficeLegacyMailIntakeBridge $bridge,
        private readonly OfficeCommunicationRepository $repository,
        private readonly ?OfficeAttachmentImportService $attachmentImporter = null
    ) {}

    /**
     * @param array{uidvalidity:int,messages:list<array{uid:int,source:array<string,mixed>}>} $batch
     * @return list<int> persisted message IDs
     */
    public function import(int $mailboxId, string $folder, array $batch): array
    {
        if ($mailboxId < 1 || $folder !== 'INBOX' || ($batch['uidvalidity'] ?? 0) < 1
            || !isset($batch['messages']) || !is_array($batch['messages'])) {
            throw new InvalidArgumentException('Invalid import batch.');
        }
        // Validate the complete batch before any write.
        foreach ($batch['messages'] as $item) {
            if (!is_array($item) || !isset($item['uid'], $item['source'])
                || !is_int($item['uid']) || $item['uid'] < 1 || !is_array($item['source'])) {
                throw new InvalidArgumentException('Invalid IMAP item.');
            }
            $source = $item['source'];
            $headers = $source['raw_headers'] ?? null;
            if (!is_string($headers) || $headers === '') {
                throw new RuntimeException('Original MIME headers required.');
            }
            if (empty($source['mime_decoded']) && (preg_match('/^Content-Type:\\s*(?:multipart\\/|message\\/|application\\/|image\\/)/im', $headers)
                || preg_match('/^Content-Transfer-Encoding:\\s*(?:base64|quoted-printable)/im', $headers)
                || preg_match('/^Content-Disposition:\\s*attachment/im', $headers)
                || preg_match('/^Content-Type:.*(?:name|filename)\\s*=/im', $headers))) {
                throw new RuntimeException('MIME decoding and attachment persistence required before import.');
            }
            if (!empty($source['attachments']) && $this->attachmentImporter === null) {
                throw new RuntimeException('Attachment importer required.');
            }
            if (!isset($source['body']) || !is_string($source['body'])) {
                throw new InvalidArgumentException('Missing message body.');
            }
        }
        $ids = [];
        foreach ($batch['messages'] as $item) {
            $source = $item['source'];
            unset($source['raw_headers']);
            $messageId = $this->bridge->import(
                $this->repository, $mailboxId, $folder,
                $batch['uidvalidity'], $item['uid'], $source
            );
            if (!empty($source['attachments'])) {
                $this->attachmentImporter->import($messageId, $source['attachments']);
            }
            $ids[] = $messageId;
        }
        return $ids;
    }
}
