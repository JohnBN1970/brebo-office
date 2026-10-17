<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;
use Throwable;

/** Independent native Office project membership; never inherits Calc-only grants. */
final class OfficeNativeProjectAccess {
  public function __construct(private readonly PDO $db) {}

  public function canRead(string $operatorId, int $projectId): bool {
    if ($operatorId === '' || strlen($operatorId) > 128 || $projectId < 1) {
      return false;
    }
    try {
      $statement = $this->db->prepare(
        'SELECT 1 FROM office_project_memberships m
         JOIN office_operator_accounts a ON a.id = m.operator_id
         JOIN office_projects p ON p.id = m.project_id
         WHERE m.operator_id = :operator_id AND m.project_id = :project_id
           AND m.permission IN (\'read\',\'manage\') AND a.active = 1 LIMIT 1'
      );
      $statement->execute([':operator_id' => $operatorId, ':project_id' => $projectId]);
      return $statement->fetchColumn() !== false;
    } catch (Throwable $exception) {
      return false;
    }
  }
  /** Only explicitly assigned project managers may change project-owned records. */
  public function canManage(string $operatorId, int $projectId): bool {
    if ($operatorId === '' || strlen($operatorId) > 128 || $projectId < 1) {
      return false;
    }
    try {
      $statement = $this->db->prepare(
        'SELECT 1 FROM office_project_memberships m
         JOIN office_operator_accounts a ON a.id = m.operator_id
         WHERE m.operator_id = :operator_id AND m.project_id = :project_id
           AND m.permission = \'manage\' AND a.active = 1 LIMIT 1'
      );
      $statement->execute([':operator_id' => $operatorId, ':project_id' => $projectId]);
      return $statement->fetchColumn() !== false;
    } catch (Throwable $exception) {
      return false;
    }
  }

}
