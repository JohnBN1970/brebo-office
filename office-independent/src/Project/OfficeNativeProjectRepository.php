<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;

/** Resolves a project from the canonical Office project ledger. */
final class OfficeNativeProjectRepository {
  public function __construct(private readonly PDO $db) {}

  public function find(int $projectId): ?OfficeCanonicalProjectContext {
    if ($projectId < 1) return null;
    $statement = $this->db->prepare(
      'SELECT id, code, title, status FROM office_projects WHERE id = :project_id LIMIT 1'
    );
    $statement->execute([':project_id' => $projectId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) return null;
    return new OfficeCanonicalProjectContext(
      new OfficeProjectIdentity((int) $row['id'], (string) $row['code']),
      (string) $row['title'],
      (string) $row['status']
    );
  }
}
