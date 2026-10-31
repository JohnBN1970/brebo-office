<?php

declare(strict_types=1);

namespace Brebo\Office\Communication;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Durable IMAP inbox. All writes use prepared statements.
 * A duplicate UID returns the existing Office message ID.
 */
final class OfficeCommunicationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
        if ($pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new InvalidArgumentException('PDO must use ERRMODE_EXCEPTION.');
        }
    }

    public function storeMessage(
        int $mailboxId,
        string $folder,
        int $uidValidity,
        int $imapUid,
        ?string $messageId,
        ?string $sender,
        ?string $subject,
        ?string $body,
        ?string $receivedAt
    ): int {
        if ($mailboxId < 1 || $uidValidity < 1 || $imapUid < 1 || trim($folder) === '') {
            throw new InvalidArgumentException('Invalid IMAP identity.');
        }
        if (strlen($folder) > 191 || ($messageId !== null && strlen($messageId) > 255)
            || ($sender !== null && strlen($sender) > 320)) {
            throw new InvalidArgumentException('IMAP metadata exceeds storage limits.');
        }

        $sql = 'INSERT INTO office_communication_messages
            (mailbox_id, folder, uidvalidity, imap_uid, source_message_id,
             sender_address, subject, body_text, received_at)
            VALUES (:mailbox, :folder, :validity, :uid, :message_id,
                    :sender, :subject, :body, :received)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':mailbox' => $mailboxId,
            ':folder' => $folder,
            ':validity' => $uidValidity,
            ':uid' => $imapUid,
            ':message_id' => $messageId,
            ':sender' => $sender,
            ':subject' => $subject,
            ':body' => $body,
            ':received' => $receivedAt,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if ($id < 1) {
            throw new RuntimeException('Message was not durably identified.');
        }
        return $id;
    }

    /**
     * Records an opaque technical error code, never exception text or mail content.
     */
    public function recordProcessingFailure(int $messageId, string $errorCode): void
    {
        if ($messageId < 1 || !preg_match('/^[a-z0-9_]{1,100}$/D', $errorCode)) {
            throw new InvalidArgumentException('Invalid processing failure.');
        }
        $stmt = $this->pdo->prepare(
            "UPDATE office_communication_messages
             SET processing_status = 'failed',
                 processing_attempts = processing_attempts + 1,
                 last_error_code = :error
             WHERE id = :id"
        );
        $stmt->execute([':error' => $errorCode, ':id' => $messageId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Message failure could not be recorded.');
        }
    }

    public function markProcessed(int $messageId): void
    {
        if ($messageId < 1) {
            throw new InvalidArgumentException('Invalid message ID.');
        }
        $stmt = $this->pdo->prepare(
            "UPDATE office_communication_messages
             SET processing_status = 'processed', last_error_code = NULL
             WHERE id = :id AND processing_status IN ('stored', 'failed')"
        );
        $stmt->execute([':id' => $messageId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Message is missing or not processable.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function pendingReview(int $limit = 50): array
    {
        if ($limit < 1 || $limit > 200) {
            throw new InvalidArgumentException('Invalid review limit.');
        }
        $stmt = $this->pdo->prepare(
            "SELECT id, mailbox_id, sender_address, subject, received_at, review_status,
                    processing_status, created_at
             FROM office_communication_messages
             WHERE review_status = 'pending'
             ORDER BY created_at DESC, id DESC LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
