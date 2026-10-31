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
        // Validate the complete batch before any write. A duplicated or
        // out-of-order UID makes cursor advancement ambiguous.
        $previousUid = 0;
        foreach ($batch['messages'] as $item) {
            if (!is_array($item) || !isset($item['uid'], $item['source'])
                || !is_int($item['uid']) || $item['uid'] < 1 || !is_array($item['source'])) {
                throw new InvalidArgumentException('Invalid IMAP item.');
            }
            if ($item['uid'] <= $previousUid) {
                throw new InvalidArgumentException('IMAP batch UIDs must be strictly increasing.');
            }
            $previousUid = $item['uid'];
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
            if (array_key_exists('attachments', $source)) {
                if (!is_array($source['attachments'])) {
                    throw new InvalidArgumentException('Invalid attachment list.');
                }
                foreach ($source['attachments'] as $attachment) {
                    if (!is_array($attachment)
                        || !isset($attachment['filename'], $attachment['mime'], $attachment['bytes'])
                        || !is_string($attachment['filename'])
                        || !is_string($attachment['mime'])
                        || !is_string($attachment['bytes'])
                        || $attachment['filename'] === ''
                        || in_array($attachment['filename'], ['.', '..'], true)
                        || str_contains($attachment['filename'], "\\0")
                        || preg_match('/[\\x00-\\x1F\\x7F]/', $attachment['filename']) === 1
                        || basename(str_replace('\\\\', '/', $attachment['filename'])) === ''
                        || strlen($attachment['filename']) > 255
                        || $attachment['mime'] === ''
                        || strlen($attachment['mime']) > 127
                        || strlen($attachment['bytes']) < 1
                        || strlen($attachment['bytes']) > 15 * 1024 * 1024) {
                        throw new InvalidArgumentException('Invalid attachment metadata in batch.');
                    }
                }
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
