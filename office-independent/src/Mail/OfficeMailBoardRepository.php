<?php
declare(strict_types=1);
namespace Brebo\Office\Mail;
use PDO;
use RuntimeException;

/** Module ownership is routing metadata, not a dossier mutation. */
final class OfficeMailBoardRepository {
  public const MODULES = ['nieuw','crm','projecten','gebouwen','finance','documenten','inspecties'];
  public function __construct(private readonly PDO $db) {}

  /** @return list<array<string,mixed>> */
  public function cards(int $operatorId): array {
    if ($operatorId <= 0) throw new RuntimeException('Office-gebruiker ontbreekt.');
    $sql = "SELECT m.id,m.subject,m.sender,m.received_at,b.label AS mailbox_label,
      COALESCE(r.module_key,'nieuw') AS module_key,
      r.suggested_module,r.suggestion_reason
      FROM office_mail_messages m JOIN office_mail_mailboxes b ON b.id=m.mailbox_id
      LEFT JOIN office_mail_board_routes r ON r.message_id=m.id
      WHERE b.active=1 AND ((b.mailbox_type='functional' AND EXISTS
        (SELECT 1 FROM office_mail_mailbox_access a WHERE a.mailbox_id=b.id
         AND a.operator_id=:operator AND a.can_read=1))
        OR (b.mailbox_type='personal' AND b.owner_operator_id=:owner))
      ORDER BY m.received_at DESC,m.id DESC LIMIT 200";
    $stmt=$this->db->prepare($sql);
    $stmt->execute([':operator'=>$operatorId,':owner'=>$operatorId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  public function move(int $messageId,int $operatorId,string $module): bool {
    if ($messageId<=0 || $operatorId<=0 || !in_array($module,self::MODULES,true)) {
      throw new RuntimeException('Ongeldige verplaatsing.');
    }
    $this->db->beginTransaction();
    try {
      $check=$this->db->prepare("SELECT m.id FROM office_mail_messages m
        JOIN office_mail_mailboxes b ON b.id=m.mailbox_id
        WHERE m.id=:message AND b.active=1
        AND ((b.mailbox_type='functional' AND EXISTS
          (SELECT 1 FROM office_mail_mailbox_access a WHERE a.mailbox_id=b.id
           AND a.operator_id=:operator AND a.can_read=1))
          OR (b.mailbox_type='personal' AND b.owner_operator_id=:owner)) FOR UPDATE");
      $check->execute([':message'=>$messageId,':operator'=>$operatorId,':owner'=>$operatorId]);
      if (!$check->fetchColumn()) { $this->db->rollBack(); return false; }
      $previous=$this->db->prepare('SELECT module_key FROM office_mail_board_routes WHERE message_id=:id');
      $previous->execute([':id'=>$messageId]);
      $old=$previous->fetchColumn();
      $from=$old===false?'nieuw':(string)$old;
      if ($from!==$module) {
        $update=$this->db->prepare("INSERT INTO office_mail_board_routes
          (message_id,module_key,changed_by_operator_id,updated_at)
          VALUES (:message,:module,:operator,UTC_TIMESTAMP())
          ON DUPLICATE KEY UPDATE module_key=VALUES(module_key),
            changed_by_operator_id=VALUES(changed_by_operator_id),updated_at=UTC_TIMESTAMP()");
        $update->execute([':message'=>$messageId,':module'=>$module,':operator'=>$operatorId]);
        $audit=$this->db->prepare("INSERT INTO office_mail_board_moves
          (message_id,from_module,to_module,operator_id,created_at)
          VALUES (:message,:from_module,:to_module,:operator,UTC_TIMESTAMP())");
        $audit->execute([':message'=>$messageId,':from_module'=>$from,':to_module'=>$module,':operator'=>$operatorId]);
      }
      $this->db->commit();
      return true;
    } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
  }
}
