<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Manager-only personnel selector. Lists employees assigned to projects that
 * the verified Office operator can manage; never enumerates the whole staff.
 */
final class OfficeInzetManageablePeopleRepository {
  public function __construct(private PDO $db) {}

  /** @return list<array<string,mixed>> */
  public function listForOperator(string $operatorId, int $projectId): array {
    if ($projectId < 1) throw new \InvalidArgumentException('Valid project required');
    if ($operatorId === '' || strlen($operatorId) > 128) {
      throw new \InvalidArgumentException('Verified operator required');
    }
    $statement = $this->db->prepare("SELECT DISTINCT p.id, p.display_name, p.personnel_number
      FROM office_inzet_people p
      INNER JOIN office_inzet_project_team t ON t.person_id = p.id
      INNER JOIN office_project_memberships m ON m.project_id = t.project_id
      INNER JOIN office_operator_accounts a ON a.id = m.operator_id
      WHERE m.operator_id = ? AND m.project_id = ? AND m.permission = 'manage' AND a.active = 1 AND p.active = 1
      ORDER BY p.display_name, p.id LIMIT 500");
    $statement->execute([$operatorId, $projectId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }
}
