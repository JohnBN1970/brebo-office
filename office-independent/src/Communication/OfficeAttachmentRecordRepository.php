<?php
declare(strict_types=1);

namespace Brebo\Office\Communication;

use InvalidArgumentException;
use PDO;

/**
 * Durable link between a stored Office message and an immutable attachment.
 * Migration for office_communication_attachments must be applied separately.
 */
final class OfficeAttachmentRecordRepository
{
    public function __construct(private readonly PDO $db) {}

    public function attach(int $messageId, string $sha256, string $filename, string $mime, int $bytes): void
    {
        if ($messageId < 1 || !preg_match('/^[a-f0-9]{64}$/D', $sha256)
            || $filename === '' || strlen($filename) > 255
            || $mime === '' || strlen($mime) > 127
            || $bytes < 1 || $bytes > 15 * 1024 * 1024) {
            throw new InvalidArgumentException('Invalid attachment metadata.');
        }
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new InvalidArgumentException('Unsupported database.');
        }
        $sql = 'INSERT INTO office_communication_attachments
            (message_id, sha256, filename, mime_type, byte_size)
            VALUES (:message_id, :sha256, :filename, :mime, :bytes)';
        $sql .= $driver === 'mysql'
            ? ' ON DUPLICATE KEY UPDATE id = id'
            : ' ON CONFLICT(message_id, sha256, filename) DO NOTHING';
        $q = $this->db->prepare($sql);
        $q->execute([
            ':message_id' => $messageId,
            ':sha256' => $sha256,
            ':filename' => $filename,
            ':mime' => $mime,
            ':bytes' => $bytes,
        ]);
    }
}
