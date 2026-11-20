<?php
declare(strict_types=1);
namespace Brebo\Office\Mail;
use PDO;

/**
 * Existing Office permissions, not a new role system.
 * CRM relations currently have no per-relation grants: require an active operator
 * and the existing CRM feature/auth flags. Do not inherit access via representations.
 */
final class OfficeMailContextAccess {
  public function __construct(private readonly PDO $db) {}

  public function canReadMessage(int $messageId, string $operatorId): bool {
    if ($messageId < 1 || $operatorId === '' || strlen($operatorId) > 128) return false;
    $stmt = $this->db->prepare("SELECT 1 FROM office_mail_messages m
      JOIN office_mail_mailboxes b ON b.id=m.mailbox_id
      WHERE m.id=:message AND b.active=1 AND (
        (b.mailbox_type='functional' AND EXISTS (
          SELECT 1 FROM office_mail_mailbox_access a
          WHERE a.mailbox_id=b.id AND a.operator_id=:operator AND a.can_read=1
        )) OR (b.mailbox_type='personal' AND b.owner_operator_id=:owner)
      ) LIMIT 1");
    $stmt->execute([':message'=>$messageId,':operator'=>$operatorId,':owner'=>$operatorId]);
    return (bool)$stmt->fetchColumn();
  }

  public function canAccessEntity(string $module, int $entityId, string $operatorId): bool {
    if ($entityId < 1 || $operatorId === '' || !ctype_digit($operatorId)) return false;
    if ($module === 'crm') {
      if (getenv('OFFICE_TEST_CRM_ENABLED') !== 'enabled'
        || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only'
        || getenv('OFFICE_CRM_RELATIONS_SCHEMA_VERIFIED') !== 'enabled') return false;
      $stmt = $this->db->prepare('SELECT 1 FROM office_operator_accounts a JOIN office_crm_relations r ON r.id=:entity WHERE a.id=:operator AND a.active=1 LIMIT 1');
      $stmt->execute([':entity'=>$entityId, ':operator'=>$operatorId]);
      return (bool)$stmt->fetchColumn();
    }
    if ($module === 'projects') {
      if (getenv('OFFICE_NATIVE_PROJECTS_ENABLED') !== 'enabled'
        || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled') return false;
      require_once dirname(__DIR__) . '/Project/OfficeNativeProjectAccess.php';
      return (new \Brebo\Office\Project\OfficeNativeProjectAccess($this->db))->canRead((int)$operatorId, $entityId);
    }
    // Calc, quotes and finance need their own verified entity ACL adapter.
    return false;
  }
}
