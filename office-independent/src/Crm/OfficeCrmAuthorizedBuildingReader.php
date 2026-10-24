<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

use PDO;

/**
 * Company buildings visible through the existing native project membership
 * model. A building without an authorized linked project is not disclosed.
 * This does not establish a standalone building-wide ACL.
 */
final class OfficeCrmAuthorizedBuildingReader {
  public function __construct(private PDO $db) {}

  /** @return list<array{id:int,role:string}> */
  public function forCompany(int $companyId, int $operatorId): array {
    if ($companyId < 1 || $operatorId < 1) return [];
    $stmt = $this->db->prepare(
      "SELECT DISTINCT cb.building_id AS id, cb.role_label AS role
       FROM office_crm_company_buildings cb
       JOIN office_project_buildings pb ON pb.building_id = cb.building_id
       JOIN office_projects p ON p.id = pb.project_id
       JOIN office_project_memberships m ON m.project_id = p.id
       JOIN office_operator_accounts a ON a.id = m.operator_id
       WHERE cb.company_id = :company_id AND m.operator_id = :operator_id
         AND m.permission IN ('read', 'manage') AND a.active = 1
       ORDER BY cb.building_id LIMIT 200"
    );
    $stmt->execute([':company_id' => $companyId, ':operator_id' => $operatorId]);
    return array_map(static fn(array $r): array => [
      'id' => (int)$r['id'], 'role' => (string)$r['role'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
  }
}
