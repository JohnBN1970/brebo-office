<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use DomainException;
use PDO;
use Throwable;

/**
 * Office-owned review queue. Caller authenticates mailbox source and operator.
 * Never changes CRM, accepts orders, or invokes external mail services.
 */
final class OfficeMailInboxRepository
{
    public function __construct(private readonly PDO $db) {}

    public function enqueue(string $source, array $mail, array $proposal): int
    {
        if (!in_array($source, ['cron', 'personal_push'], true)
            || ($proposal['status'] ?? null) !== 'PENDING_HUMAN_REVIEW'
            || ($proposal['canonical_office_only'] ?? null) !== true
            || ($mail['message_id'] ?? null) !== ($proposal['message_id'] ?? null)) {
            throw new DomainException('untrusted_mail_envelope');
        }
        $sender = $mail['sender_address'] ?? null;
        $subject = $mail['subject'] ?? null;
        $received = $mail['received_at'] ?? null;
        if (!is_string($sender) || !filter_var($sender, FILTER_VALIDATE_EMAIL)
            || !is_string($subject) || trim($subject) === '' || mb_strlen($subject) > 500
            || !is_string($received) || !preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/D', $received)) {
            throw new DomainException('invalid_mail_metadata');
        }
        $stmt = $this->db->prepare(
            'INSERT INTO office_mail_review_items
             (source, source_message_id, sender_address, subject, received_at,
              intent, confidence, suggested_action, review_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $stmt->execute([
            $source, $proposal['message_id'], $sender, trim($subject), $received,
            $proposal['intent'], $proposal['confidence'], $proposal['suggested_action'], 'pending',
        ]);
        $id = (int)$this->db->lastInsertId();
        if ($id < 1) throw new DomainException('mail_queue_identity_unavailable');
        return $id;
    }

    public function pending(int $limit = 50): array
    {
        if ($limit < 1 || $limit > 100) throw new DomainException('invalid_limit');
        $stmt = $this->db->prepare(
            'SELECT id, source, sender_address, subject, received_at, intent,
                    confidence, suggested_action, review_status
             FROM office_mail_review_items
             WHERE review_status = ? ORDER BY received_at DESC, id DESC LIMIT ' . $limit
        );
        $stmt->execute(['pending']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Review decision only. Business actions require a separate authorized handler. */
    public function decide(int $id, int $reviewerId, string $decision): bool
    {
        if ($id < 1 || $reviewerId < 1 || !in_array($decision, ['approved', 'rejected'], true)) {
            throw new DomainException('invalid_review_decision');
        }
        $stmt = $this->db->prepare(
            'UPDATE office_mail_review_items SET review_status = ?, reviewed_by = ?,
                    reviewed_at = UTC_TIMESTAMP()
             WHERE id = ? AND review_status = ?'
        );
        $stmt->execute([$decision, $reviewerId, $id, 'pending']);
        return $stmt->rowCount() === 1;
    }
}
