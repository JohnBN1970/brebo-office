<?php
declare(strict_types=1);

namespace Brebo\Office\Communication;

use InvalidArgumentException;

/**
 * Translates the legacy brebo_mail_intake IMAP payload to native Office storage.
 * Pure mapping only: no Drupal dependency, no provider mutation, no mail send.
 * Caller must obtain a real UIDVALIDITY and UID from its authenticated source.
 */
final class OfficeLegacyMailIntakeBridge
{
    /** @param array<string,mixed> $legacy */
    public function import(
        OfficeCommunicationRepository $repository,
        int $mailboxId,
        string $folder,
        int $uidValidity,
        int $imapUid,
        array $legacy
    ): int {
        foreach (['subject', 'body', 'from', 'received_at', 'body_html'] as $field) {
            if (isset($legacy[$field]) && !is_string($legacy[$field])) {
                throw new InvalidArgumentException('Invalid legacy mail field: ' . $field);
            }
        }
        if (isset($legacy['attachments']) && !is_array($legacy['attachments'])) {
            throw new InvalidArgumentException('Invalid attachment metadata.');
        }
        $sourceId = $legacy['source_id'] ?? null;
        if ($sourceId !== null && !is_string($sourceId)) {
            throw new InvalidArgumentException('Invalid source identity.');
        }
        $messageId = is_string($sourceId) && str_starts_with($sourceId, 'message-id:')
            ? substr($sourceId, strlen('message-id:')) : null;
        return $repository->storeMessage(
            $mailboxId,
            $folder,
            $uidValidity,
            $imapUid,
            $messageId,
            $legacy['from'] ?? null,
            $legacy['subject'] ?? null,
            $legacy['body'] ?? null,
            $legacy['received_at'] ?? null,
            $legacy['body_html'] ?? null
        );
    }
}
