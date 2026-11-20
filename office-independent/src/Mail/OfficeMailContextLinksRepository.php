<?php
declare(strict_types=1);
namespace Brebo\Office\Mail;
use PDO;
use RuntimeException;

/** Context links never grant access: caller must enforce mailbox AND entity ACL. */
final class OfficeMailContextLinksRepository {
  private const MODULES = ['crm', 'leads', 'projects', 'calculations', 'quotes', 'finance'];
  public function __construct(private readonly PDO $db) {}

  /** Only call after authorization for BOTH message and target entity. */
  public function link(int $messageId, string $module, int $entityId, string $actor, callable $canReadMessage, callable $canAccessEntity): void {
    $this->validate($messageId, $module, $entityId);
    if ($actor === '' || strlen($actor) > 128 || !$canReadMessage($messageId, $actor) || !$canAccessEntity($module, $entityId, $actor)) {
      throw new RuntimeException('Geen toegang tot bericht of module.');
    }
    $insert = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
    $stmt = $this->db->prepare($insert . ' INTO office_mail_context_links (message_id,module_key,entity_id,linked_by) VALUES (:m,:k,:e,:a)');
    $this->db->beginTransaction();
    try {
      $stmt->execute([':m'=>$messageId, ':k'=>$module, ':e'=>$entityId, ':a'=>$actor]);
      if ($stmt->rowCount() > 0) $this->audit($messageId,$module,$entityId,$actor,'linked');
      $this->db->commit();
    } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
  }

  /** Filter both access domains BEFORE returning any mail metadata. */
  public function forEntity(string $module, int $entityId, string $actor, callable $canAccessEntity, callable $canReadMessage): array {
    if (!in_array($module, self::MODULES, true) || $entityId < 1 || $actor === '' || !$canAccessEntity($module, $entityId, $actor)) {
      throw new RuntimeException('Geen toegang tot module.');
    }
    $stmt = $this->db->prepare('SELECT message_id FROM office_mail_context_links WHERE module_key=:k AND entity_id=:e ORDER BY id DESC LIMIT 100');
    $stmt->execute([':k'=>$module, ':e'=>$entityId]);
    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
      if ($canReadMessage((int)$id, $actor)) $ids[] = (int)$id;
    }
    return $ids;
  }

  /** Unlink is not message deletion. */
  public function unlink(int $messageId, string $module, int $entityId, string $actor, callable $canReadMessage, callable $canAccessEntity): void {
    $this->validate($messageId, $module, $entityId);
    if ($actor === '' || !$canReadMessage($messageId, $actor) || !$canAccessEntity($module, $entityId, $actor)) throw new RuntimeException('Geen toegang.');
    $stmt = $this->db->prepare('DELETE FROM office_mail_context_links WHERE message_id=:m AND module_key=:k AND entity_id=:e');
    $this->db->beginTransaction();
    try {
      $stmt->execute([':m'=>$messageId, ':k'=>$module, ':e'=>$entityId]);
      if ($stmt->rowCount() > 0) $this->audit($messageId,$module,$entityId,$actor,'unlinked');
      $this->db->commit();
    } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
  }
  private function audit(int $messageId,string $module,int $entityId,string $actor,string $action): void {
    $stmt=$this->db->prepare('INSERT INTO office_mail_context_link_events (message_id,module_key,entity_id,actor_id,action) VALUES (:m,:k,:e,:a,:action)');
    $stmt->execute([':m'=>$messageId,':k'=>$module,':e'=>$entityId,':a'=>$actor,':action'=>$action]);
  }
  private function validate(int $messageId, string $module, int $entityId): void {
    if ($messageId < 1 || $entityId < 1 || !in_array($module, self::MODULES, true)) throw new RuntimeException('Ongeldige mailkoppeling.');
  }
}
