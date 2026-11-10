<?php
declare(strict_types=1);

namespace Brebo\Office\Dashboard;

use PDO;
use RuntimeException;

/**
 * Read-only KPI provider using the existing Office project-membership ACL.
 * Never aggregates projects that the authenticated operator cannot read.
 */
final class OfficeDashboardKpis {
  public function __construct(private PDO $db) {}

  /** @return array<string,array{label:string,value:string,note:string}> */
  public function forOperator(string $operatorId): array {
    if (!ctype_digit($operatorId) || (int)$operatorId < 1) {
      throw new RuntimeException('Authenticated Office operator required');
    }
    $query = $this->db->prepare(
      "SELECT COUNT(DISTINCT p.id) FROM office_projects p
       INNER JOIN office_project_memberships m ON m.project_id=p.id
       INNER JOIN office_operator_accounts a ON a.id=m.operator_id
       WHERE m.operator_id=:operator AND m.permission IN ('read','manage') AND a.active=1"
    );
    $query->execute([':operator' => $operatorId]);
    $raw = $query->fetchColumn();
    if (!is_int($raw) && (!is_string($raw) || !ctype_digit($raw))) {
      throw new RuntimeException('Project count unavailable');
    }
    return ['projects' => [
      'label' => 'Mijn projecten',
      'value' => (string)$raw,
      'note' => 'Projecten met actieve Office-toegang',
    ]];
  }
}
