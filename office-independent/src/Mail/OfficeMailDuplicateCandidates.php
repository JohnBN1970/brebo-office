<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use PDO;
use RuntimeException;

/**
 * Read-only candidate discovery within mailboxes visible to the current operator.
 * A matching subject/sender is a review hint, NEVER proof of a duplicate project.
 */
final class OfficeMailDuplicateCandidates
{
    public function __construct(private readonly PDO $db) {}

    /** @return list<array{id:int,subject:string,sender:string,received_at:string,reason:string}> */
    public function forMessage(int $messageId, string $operatorId, int $limit = 10): array
    {
        if ($messageId < 1 || $operatorId === '' || strlen($operatorId) > 128 || $limit < 1 || $limit > 25) {
            throw new RuntimeException('Ongeldige zoekopdracht.');
        }
        $access = "b.active=1 AND ((b.mailbox_type='functional' AND EXISTS
            (SELECT 1 FROM office_mail_mailbox_access a WHERE a.mailbox_id=b.id
             AND a.operator_id=:operator AND a.can_read=1))
            OR (b.mailbox_type='personal' AND b.owner_operator_id=:owner))";
        $source = $this->db->prepare("SELECT m.id,m.mailbox_id,m.sender,m.subject,b.mailbox_type
            FROM office_mail_messages m JOIN office_mail_mailboxes b ON b.id=m.mailbox_id
            WHERE m.id=:message AND $access LIMIT 1");
        $source->execute([':message'=>$messageId,':operator'=>$operatorId,':owner'=>$operatorId]);
        $mail = $source->fetch(PDO::FETCH_ASSOC);
        if (!$mail) return [];
        $sender = mb_strtolower(trim((string)$mail['sender']), 'UTF-8');
        $subject = mb_strtolower(trim((string)$mail['subject']), 'UTF-8');
        $subject = preg_replace('/^(?:(?:re|fw|fwd)\s*:\s*)+/iu', '', $subject) ?? $subject;
        if ($sender === '' || $subject === '') return [];

        // Restrict personal mail to the same personal mailbox, even when the operator
        // also has functional mailbox access.
        $sql = "SELECT m.id,m.subject,m.sender,m.received_at
            FROM office_mail_messages m JOIN office_mail_mailboxes b ON b.id=m.mailbox_id
            WHERE m.id<>:message AND $access
            AND (:personal=0 OR m.mailbox_id=:mailbox)
            ORDER BY m.received_at DESC,m.id DESC LIMIT 500";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':message'=>$messageId, ':operator'=>$operatorId, ':owner'=>$operatorId,
            ':personal'=>$mail['mailbox_type']==='personal'?1:0, ':mailbox'=>(int)$mail['mailbox_id']
        ]);
        $matches=[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
            if (mb_strtolower(trim((string)$candidate['sender']), 'UTF-8') !== $sender) continue;
            $other = mb_strtolower(trim((string)$candidate['subject']), 'UTF-8');
            $other = preg_replace('/^(?:(?:re|fw|fwd)\s*:\s*)+/iu', '', $other) ?? $other;
            if ($other !== $subject) continue;
            $matches[] = [
                'id'=>(int)$candidate['id'], 'subject'=>(string)$candidate['subject'],
                'sender'=>(string)$candidate['sender'], 'received_at'=>(string)$candidate['received_at'],
                'reason'=>'Zelfde afzender en genormaliseerd onderwerp (mogelijke vervolgcorrespondentie)'
            ];
            if (count($matches) >= $limit) break;
        }
        return $matches;
    }
}
