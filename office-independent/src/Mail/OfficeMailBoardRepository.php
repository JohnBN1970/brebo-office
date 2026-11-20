<?php
declare(strict_types=1);
namespace Brebo\Office\Mail;
use PDO;
use RuntimeException;

/** Module ownership is routing metadata, not a dossier mutation. */
final class OfficeMailBoardRepository {
  public const MODULES = ['nieuw','prive','crm','projecten','finance','documenten','inspecties'];
  public function __construct(private readonly PDO $db) {}

  /** @return list<array<string,mixed>> */
  public function cards(string $operatorId, int $page = 1, string $search = ''): array {
    $page = max(1, min(100000, $page));
    $offset = ($page - 1) * 200;
    if ($operatorId === '' || strlen($operatorId) > 128) throw new RuntimeException('Office-gebruiker ontbreekt.');
    $search = substr(trim($search), 0, 120);
    $sql = "SELECT m.id,m.mailbox_id,m.subject,m.sender,m.received_at,b.label AS mailbox_label,
      CASE WHEN r.module_key='gebouwen' THEN 'nieuw' ELSE COALESCE(r.module_key,CASE WHEN b.mailbox_type='personal' THEN 'prive' ELSE 'nieuw' END) END AS module_key,
      r.suggested_module,r.suggestion_reason
      FROM office_mail_messages m JOIN office_mail_mailboxes b ON b.id=m.mailbox_id
      LEFT JOIN office_mail_board_routes r ON r.message_id=m.id
      WHERE b.active=1 AND ((b.mailbox_type='functional' AND EXISTS
        (SELECT 1 FROM office_mail_mailbox_access a WHERE a.mailbox_id=b.id
         AND a.operator_id=:operator AND a.can_read=1))
        OR (b.mailbox_type='personal' AND b.owner_operator_id=:owner))
      " . ($search !== '' ? " AND (m.subject LIKE :search_subject OR m.sender LIKE :search_sender) " : "") . "
      ORDER BY m.received_at DESC,m.id DESC LIMIT 200 OFFSET " . $offset;
    $stmt=$this->db->prepare($sql);
    $params=[':operator'=>$operatorId,':owner'=>$operatorId];
    if ($search !== '') { $params[':search_subject']='%'.$search.'%'; $params[':search_sender']='%'.$search.'%'; }
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }


  /** ACL-scoped counts across all messages, not just the current page. */
  public function folderCounts(string $operatorId, ?int $mailboxId, bool $statesEnabled): array {
    $join = $statesEnabled ? " LEFT JOIN office_mail_message_states st ON st.message_id=m.id AND st.operator_id=:state_operator " : "";
    $folder = $statesEnabled ? "COALESCE(st.folder,'inbox')" : "'inbox'";
    $read = $statesEnabled ? "COALESCE(st.is_read,0)" : "0";
    $sql = "SELECT ".$folder." AS folder, ".$read." AS is_read, COUNT(*) AS total
      FROM office_mail_messages m JOIN office_mail_mailboxes b ON b.id=m.mailbox_id ".$join."
      WHERE b.active=1 AND ((b.mailbox_type='functional' AND EXISTS
        (SELECT 1 FROM office_mail_mailbox_access a WHERE a.mailbox_id=b.id AND a.operator_id=:operator AND a.can_read=1))
        OR (b.mailbox_type='personal' AND b.owner_operator_id=:owner))"
      . ($mailboxId !== null ? " AND m.mailbox_id=:mailbox" : "")
      . " GROUP BY ".$folder.", ".$read;
    $params=[':operator'=>$operatorId,':owner'=>$operatorId];
    if ($statesEnabled) $params[':state_operator']=$operatorId;
    if ($mailboxId !== null) $params[':mailbox']=$mailboxId;
    $stmt=$this->db->prepare($sql);
    $stmt->execute($params);
    $counts=['inbox'=>0,'unread'=>0,'archive'=>0,'spam'=>0,'trash'=>0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $folderName=(string)$row['folder'];
      if (isset($counts[$folderName])) $counts[$folderName]+=(int)$row['total'];
      if ($folderName==='inbox' && (int)$row['is_read']===0) $counts['unread']+=(int)$row['total'];
    }
    return $counts;
  }

  /** Filter before LIMIT to avoid empty pages for selected folders/accounts. */
  public function inboxCards(string $operatorId, int $page, string $search, string $folder, ?int $mailboxId, bool $statesEnabled): array {
    $page=max(1,min(100000,$page));
    $search=substr(trim($search),0,120);
    $join=$statesEnabled ? " LEFT JOIN office_mail_message_states st ON st.message_id=m.id AND st.operator_id=:state_operator " : "";
    $folderExpr=$statesEnabled ? "COALESCE(st.folder,'inbox')" : "'inbox'";
    $readExpr=$statesEnabled ? "COALESCE(st.is_read,0)" : "0";
    $sql="SELECT m.id,m.mailbox_id,m.subject,m.sender,m.received_at,b.label AS mailbox_label,
      CASE WHEN r.module_key='gebouwen' THEN 'nieuw' ELSE COALESCE(r.module_key,CASE WHEN b.mailbox_type='personal' THEN 'prive' ELSE 'nieuw' END) END AS module_key,
      r.suggested_module,r.suggestion_reason
      FROM office_mail_messages m JOIN office_mail_mailboxes b ON b.id=m.mailbox_id
      LEFT JOIN office_mail_board_routes r ON r.message_id=m.id ".$join."
      WHERE b.active=1 AND ((b.mailbox_type='functional' AND EXISTS
        (SELECT 1 FROM office_mail_mailbox_access a WHERE a.mailbox_id=b.id AND a.operator_id=:operator AND a.can_read=1))
        OR (b.mailbox_type='personal' AND b.owner_operator_id=:owner))";
    $params=[':operator'=>$operatorId,':owner'=>$operatorId];
    if ($statesEnabled) $params[':state_operator']=$operatorId;
    if ($mailboxId !== null) { $sql.=" AND m.mailbox_id=:mailbox"; $params[':mailbox']=$mailboxId; }
    if ($folder==='unread') $sql.=" AND ".$folderExpr."='inbox' AND ".$readExpr."=0";
    else { $sql.=" AND ".$folderExpr."=:folder"; $params[':folder']=$folder; }
    if ($search!=='') {
      $sql.=" AND (m.subject LIKE :subject_search OR m.sender LIKE :sender_search)";
      $params[':subject_search']='%'.$search.'%';
      $params[':sender_search']='%'.$search.'%';
    }
    $sql.=" ORDER BY m.received_at DESC,m.id DESC LIMIT 200 OFFSET ".(($page-1)*200);
    $stmt=$this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  public function move(int $messageId,string $operatorId,string $module): bool {
    if ($messageId <= 0 || $operatorId === '' || strlen($operatorId) > 128 || !in_array($module,self::MODULES,true)) {
      throw new RuntimeException('Ongeldige verplaatsing.');
    }
    $this->db->beginTransaction();
    try {
      $check=$this->db->prepare("SELECT m.id,b.mailbox_type FROM office_mail_messages m
        JOIN office_mail_mailboxes b ON b.id=m.mailbox_id
        WHERE m.id=:message AND b.active=1
        AND ((b.mailbox_type='functional' AND EXISTS
          (SELECT 1 FROM office_mail_mailbox_access a WHERE a.mailbox_id=b.id
           AND a.operator_id=:operator AND a.can_read=1))
          OR (b.mailbox_type='personal' AND b.owner_operator_id=:owner)) FOR UPDATE");
      $check->execute([':message'=>$messageId,':operator'=>$operatorId,':owner'=>$operatorId]);
      $mail = $check->fetch(PDO::FETCH_ASSOC);
      if (!$mail) { $this->db->rollBack(); return false; }
      // A personal message cannot be silently shared by dragging it into a functional lane.
      if ($mail['mailbox_type'] === 'personal' && $module !== 'prive') {
        $this->db->rollBack(); return false;
      }
      if ($mail['mailbox_type'] === 'functional' && $module === 'prive') {
        $this->db->rollBack(); return false;
      }
      $previous=$this->db->prepare('SELECT module_key FROM office_mail_board_routes WHERE message_id=:id FOR UPDATE');
      $previous->execute([':id'=>$messageId]);
      $old=$previous->fetchColumn();
      $from=$old===false?($mail['mailbox_type']==='personal'?'prive':'nieuw'):(string)$old;
      if ($from === 'gebouwen') $from = 'nieuw';
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
