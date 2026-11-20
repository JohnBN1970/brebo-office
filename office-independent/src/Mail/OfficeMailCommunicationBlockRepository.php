<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use PDO;
use DomainException;

/**
 * Native Office mail-context repository.
 *
 * Schema is installed separately. Callers authenticate reviewers and verify
 * canonical target identities before saving. Reads require both ACL callbacks.
 */
final class OfficeMailCommunicationBlockRepository
{
    public function __construct(private PDO $db) {}

    public static function schemaSql(): string
    {
        return 'CREATE TABLE IF NOT EXISTS office_mail_communication_links (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            message_id VARCHAR(255) NOT NULL,
            module VARCHAR(48) NOT NULL,
            object_type VARCHAR(48) NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            confirmed_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY office_mail_link_unique (message_id, module, object_type, object_id),
            KEY office_mail_link_target (module, object_type, object_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    }

    /** Persist only human-verified links; no mail content or attachments. */
    public function saveConfirmed(array $prepared, callable $canReadMail, callable $canLinkTarget): int
    {
        if (($prepared['requires_destination_acl'] ?? null) !== true
            || ($prepared['requires_source_mail_acl'] ?? null) !== true
            || ($prepared['creates_business_transition'] ?? null) !== false) {
            throw new DomainException('invalid_mail_link_handoff');
        }
        $messageId = $prepared['source_message_id'] ?? null;
        if (!is_string($messageId) || $messageId === '' || strlen($messageId) > 255) {
            throw new DomainException('invalid_source_message');
        }
        $links = $prepared['links'] ?? null;
        if (!is_array($links)) {
            throw new DomainException('invalid_mail_links');
        }
        if ($canReadMail($messageId) !== true) {
            throw new DomainException('mail_access_denied');
        }
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO office_mail_communication_links
                (message_id, module, object_type, object_id, confirmed_by, created_at)
                VALUES (:message, :module, :type, :id, :reviewer, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE id = id');
            foreach ($links as $link) {
                $module = $link['module'] ?? null;
                $type = $link['object_type'] ?? null;
                $id = $link['object_id'] ?? null;
                $reviewer = $link['confirmed_by'] ?? null;
                if (($link['state'] ?? null) !== 'confirmed'
                    || !is_string($module) || !preg_match('/^[a-z][a-z0-9_]{0,47}$/D', $module)
                    || !is_string($type) || !preg_match('/^[a-z][a-z0-9_]{0,47}$/D', $type)
                    || !is_int($id) || $id < 1 || !is_int($reviewer) || $reviewer < 1) {
                    throw new DomainException('invalid_confirmed_mail_link');
                }
                // Reject forged prepared payloads: only canonical Office dossier types are linkable.
                $allowedTargets = [
                    'crm' => ['relation', 'deal'],
                    'projecten' => ['project'],
                    'calculatie' => ['calculation'],
                    'finance' => ['invoice'],
                    'documenten' => ['document'],
                    'acties' => ['action'],
                ];
                if (!in_array($type, $allowedTargets[$module] ?? [], true)) {
                    throw new DomainException('unsupported_mail_link_target');
                }
                if ($canLinkTarget($module, $type, $id, $reviewer) !== true) {
                    throw new DomainException('mail_target_access_denied');
                }
                $stmt->execute([
                    'message' => $messageId, 'module' => $module,
                    'type' => $type, 'id' => $id, 'reviewer' => $reviewer,
                ]);
            }
            $this->db->commit();
            return count($links);
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    /**
     * Return only links authorized by BOTH the mail and target access policies.
     * Callbacks receive (messageId) and (module, objectType, objectId).
     */
    public function visibleFor(string $module, string $type, int $objectId, callable $canReadMail, callable $canReadTarget): array
    {
        if ($objectId < 1 || !preg_match('/^[a-z][a-z0-9_]{0,47}$/D', $module)
            || !preg_match('/^[a-z][a-z0-9_]{0,47}$/D', $type)) {
            throw new DomainException('invalid_mail_target');
        }
        if ($canReadTarget($module, $type, $objectId) !== true) {
            return [];
        }
        $stmt = $this->db->prepare('SELECT message_id, module, object_type, object_id
            FROM office_mail_communication_links
            WHERE module = :module AND object_type = :type AND object_id = :id
            ORDER BY id DESC LIMIT 100');
        $stmt->execute(['module' => $module, 'type' => $type, 'id' => $objectId]);
        $visible = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($canReadMail($row['message_id']) === true) {
                $visible[] = $row;
            }
        }
        return $visible;
    }
}
