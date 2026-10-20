<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;

/** Office-native authorization; project membership is not zone ownership. */
final class OfficeNativeProjectAccess {
  public function __construct(private PDO $db) {}

  public function canManage(string|int $operatorId, int $projectId): bool {
    if ((string)$operatorId === '' || strlen((string)$operatorId) > 128 || $projectId < 1) return false;
    $statement = $this->db->prepare(
      "SELECT 1 FROM office_projects p
       INNER JOIN office_project_memberships m ON m.project_id=p.id
       INNER JOIN office_operator_accounts a ON a.id=m.operator_id
       WHERE p.id=:project_id AND m.operator_id=:operator_id
         AND m.permission='manage' AND a.active=1 LIMIT 1"
    );
    $statement->execute([':project_id'=>$projectId,':operator_id'=>$operatorId]);
    return $statement->fetchColumn()!==false;
  }
}
