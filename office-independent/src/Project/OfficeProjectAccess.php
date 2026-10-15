<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;
use Throwable;

/** Read-only project access through existing Office Calc project grants. */
final class OfficeProjectAccess {
  public function __construct(private readonly PDO $db) {}

  public function canRead(string $operatorId, OfficeProjectIdentity $project): bool {
    if ($operatorId === '' || strlen($operatorId) > 128) {
      return false;
    }
    try {
      $statement = $this->db->prepare(
        'SELECT 1 FROM office_calc_project_access g
         JOIN office_operator_accounts a ON a.id = g.operator_id
         WHERE g.operator_id = :operator_id AND g.project_id = :project_id
         AND a.active = 1 LIMIT 1'
      );
      $statement->execute([':operator_id' => $operatorId, ':project_id' => $project->projectId]);
      return $statement->fetchColumn() !== false;
    } catch (Throwable $exception) {
      return false;
    }
  }
}
