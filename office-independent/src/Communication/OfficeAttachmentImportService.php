<?php
declare(strict_types=1);

namespace Brebo\Office\Communication;

use InvalidArgumentException;

/**
 * Persist extracted MIME attachment bytes and register them against a durable
 * Office message. Call only after the message has been stored.
 * The caller must retry the whole message if any attachment fails.
 */
final class OfficeAttachmentImportService
{
    public function __construct(
        private readonly OfficeAttachmentVault $vault,
        private readonly OfficeAttachmentRecordRepository $records
    ) {}

    /**
     * @param list<array{filename:string,mime:string,bytes:string}> $attachments
     * @return list<array{sha256:string,bytes:int}>
     */
    public function import(int $messageId, array $attachments): array
    {
        if ($messageId < 1) {
            throw new InvalidArgumentException('Invalid Office message ID.');
        }
        $result = [];
        foreach ($attachments as $attachment) {
            if (!isset($attachment['filename'], $attachment['mime'], $attachment['bytes'])
                || !is_string($attachment['filename'])
                || !is_string($attachment['mime'])
                || !is_string($attachment['bytes'])) {
                throw new InvalidArgumentException('Invalid extracted attachment.');
            }
            $filename = basename(str_replace('\\', '/', $attachment['filename']));
            if ($filename === '' || $filename === '.' || $filename === '..') {
                throw new InvalidArgumentException('Invalid attachment filename.');
            }
            $saved = $this->vault->put($attachment['bytes']);
            $this->records->attach(
                $messageId, $saved['sha256'], $filename,
                $attachment['mime'], $saved['bytes']
            );
            $result[] = ['sha256' => $saved['sha256'], 'bytes' => $saved['bytes']];
        }
        return $result;
    }
}
