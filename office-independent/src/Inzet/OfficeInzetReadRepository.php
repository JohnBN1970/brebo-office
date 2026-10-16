<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use Brebo\Office\Project\OfficeNativeProjectAccess;
use PDO;

/**
 * Read-only Office Inzet projection. Fails closed until the reviewed schema
 * has been installed in the isolated test database.
 */
final class OfficeInzetReadRepository {
  private readonly OfficeNativeProjectAccess $access;

  public function __construct(private readonly PDO $db) {
    $this->access = new OfficeNativeProjectAccess($db);
  }

  /** @return list<array<string,mixed>> */
  public function teamForProject(string $operatorId, int $projectId): array {
    if (!$this->access->canRead($operatorId, $projectId)) return [];
    $stmt = $this->db->prepare(
      'SELECT p.id, p.personnel_number, p.display_name, t.role_label
       FROM office_inzet_project_team t
       INNER JOIN office_inzet_people p ON p.id = t.person_id
       WHERE t.project_id = :project AND p.active = 1
       ORDER BY p.display_name, p.id LIMIT 200'
    );
    $stmt->execute([':project' => $projectId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return list<array<string,mixed>> */
  public function shiftsForProject(string $operatorId, int $projectId): array {
    if (!$this->access->canRead($operatorId, $projectId)) return [];
    $stmt = $this->db->prepare(
      'SELECT s.id, s.project_id, s.person_id, p.display_name,
              s.starts_at, s.ends_at, s.status
       FROM office_inzet_shifts s
       LEFT JOIN office_inzet_people p ON p.id = s.person_id
       WHERE s.project_id = :project
       ORDER BY s.starts_at, s.id LIMIT 500'
    );
    $stmt->execute([':project' => $projectId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
