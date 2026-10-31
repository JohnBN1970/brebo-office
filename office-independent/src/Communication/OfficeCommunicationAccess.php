<?php
declare(strict_types=1);
namespace Brebo\Office\Communication;
use PDO;

/** Explicit Office operator ACL; no implicit CRM or Finance grants. */
final class OfficeCommunicationAccess {
  public function __construct(private readonly PDO $db, private readonly string $operatorId) {}
  public function allows(string $permission = 'read'): bool {
    if (!in_array($permission, ['read', 'review'], true)) return false;
    $q = $this->db->prepare("SELECT 1 FROM office_module_permissions g
      INNER JOIN office_operator_accounts a ON a.id = g.operator_id
      WHERE g.operator_id = ? AND g.module = 'communication'
        AND g.permission = ? AND a.active = 1 LIMIT 1");
    $q->execute([$this->operatorId, $permission]);
    return $q->fetchColumn() !== false;
  }
}
