<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;
use Throwable;

/**
 * Calc-scoped project IDs only. Not a general project directory or dossier ACL.
 * Canonical project metadata must come from a separately verified source.
 */
final class OfficeCalcScopedProjectList {
  public function __construct(private readonly PDO $db) {}

  /** @return list<int> */
  public function idsForOperator(string $operatorId, int $limit = 50): array {
    if ($operatorId === '' || strlen($operatorId) > 128 || $limit < 1 || $limit > 100) {
      return [];
    }
    try {
      $statement = $this->db->prepare(
        'SELECT DISTINCT g.project_id FROM office_calc_project_access g
         JOIN office_operator_accounts a ON a.id = g.operator_id
         WHERE g.operator_id = :operator_id AND a.active = 1 AND g.project_id > 0
         ORDER BY g.project_id ASC LIMIT ' . $limit
      );
      $statement->execute([':operator_id' => $operatorId]);
      $ids = $statement->fetchAll(PDO::FETCH_COLUMN);
      return array_values(array_map('intval', $ids));
    } catch (Throwable $exception) {
      return [];
    }
  }
}
