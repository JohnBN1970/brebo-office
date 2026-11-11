<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use PDO;
use RuntimeException;

/** Idempotent Office-only classification proposals. Never changes dossiers. */
final class OfficeMailClassificationRepository {
  public function __construct(private readonly PDO $db) {}

  /** @return array{id:int,status:string} */
  public function propose(int $messageId, string $module, ?int $targetId, string $reason): array {
    if ($messageId <= 0 || !in_array($module, ['crm','projecten','gebouwen','finance','documenten','inspecties','onbekend'], true)
      || ($targetId !== null && $targetId <= 0) || strlen($reason) > 1000) {
      throw new RuntimeException('Ongeldig mailvoorstel.');
    }
    $sql = "INSERT INTO office_mail_classification_proposals
      (message_id, proposed_module, proposed_target_id, reason, status, created_at)
      VALUES (:message, :module, :target, :reason, 'pending_review', UTC_TIMESTAMP())
      ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':message'=>$messageId, ':module'=>$module, ':target'=>$targetId, ':reason'=>$reason]);
    $id = (int) $this->db->lastInsertId();
    $read = $this->db->prepare('SELECT status FROM office_mail_classification_proposals WHERE id = :id');
    $read->execute([':id'=>$id]);
    return ['id'=>$id, 'status'=>(string)$read->fetchColumn()];
  }

  /** @return list<array<string,mixed>> */
  public function pendingForOperator(int $operatorId, int $limit=50): array {
    if ($operatorId <= 0) throw new RuntimeException('Office-gebruiker ontbreekt.');
    $limit = max(1,min(100,$limit));
    $sql = "SELECT p.id,p.message_id,p.proposed_module,p.proposed_target_id,p.reason,
      m.subject,m.sender,m.received_at,b.label AS mailbox_label
      FROM office_mail_classification_proposals p
      INNER JOIN office_mail_messages m ON m.id=p.message_id
      INNER JOIN office_mail_mailboxes b ON b.id=m.mailbox_id
      WHERE p.status='pending_review' AND b.active=1
      AND ((b.mailbox_type='functional' AND EXISTS
        (SELECT 1 FROM office_mail_mailbox_access a
         WHERE a.mailbox_id=b.id AND a.operator_id=:operator AND a.can_read=1))
        OR (b.mailbox_type='personal' AND b.owner_operator_id=:owner))
      ORDER BY p.id DESC LIMIT ".$limit;
    $stmt=$this->db->prepare($sql);
    $stmt->execute([':operator'=>$operatorId,':owner'=>$operatorId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
}
