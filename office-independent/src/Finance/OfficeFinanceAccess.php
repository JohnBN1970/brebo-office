<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use PDO;

/** Live Office ACL. CRM edit, project management and source user IDs grant no Finance access. */
final class OfficeFinanceAccess {
  public function __construct(private readonly PDO $db, private readonly string $operatorId) {}

  public function allows(string $permission = 'read'): bool {
    if (!in_array($permission, ['read', 'unassigned_read'], true)) return false;
    $q = $this->db->prepare("SELECT 1 FROM office_module_permissions g
      INNER JOIN office_operator_accounts a ON a.id=g.operator_id
      WHERE g.operator_id=? AND g.module='finance' AND g.permission=? AND a.active=1 LIMIT 1");
    $q->execute([$this->operatorId, $permission]);
    return $q->fetchColumn() !== false;
  }

  /** Office-native draft assignments do not require legacy Finance source mappings. */
  public function draftProjects(): array {
    if (!$this->allows()) return [];
    $q = $this->db->prepare("SELECT DISTINCT p.id AS office_project_id, p.title
      FROM office_projects p
      INNER JOIN office_project_memberships m ON m.project_id=p.id
      INNER JOIN office_operator_accounts a ON a.id=m.operator_id
      WHERE m.operator_id=? AND m.permission IN ('read','manage') AND a.active=1
      ORDER BY p.title,p.id");
    $q->execute([$this->operatorId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
  }

  /** Source project identifiers are never assumed to equal Office project identifiers. */
  public function visibleProjects(): array {
    if (!$this->allows()) return [];
    $q = $this->db->prepare("SELECT f.source_project_id, p.id AS office_project_id, p.title
      FROM office_finance_project_mapping f
      INNER JOIN office_projects p ON p.id=f.office_project_id
      INNER JOIN office_project_memberships m ON m.project_id=p.id
      INNER JOIN office_operator_accounts a ON a.id=m.operator_id
      WHERE m.operator_id=? AND m.permission IN ('read','manage') AND a.active=1 AND f.verified=1");
    $q->execute([$this->operatorId]);
    $result = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $result[(int)$row['source_project_id']] = $row;
    return $result;
  }
}
