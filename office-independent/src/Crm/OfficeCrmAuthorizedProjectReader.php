<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

use PDO;

/**
 * Fail-closed project relationship reader for an authenticated Office operator.
 * Never expose CRM-linked project IDs unless the active operator has
 * read/manage membership in the native project access model.
 */
final class OfficeCrmAuthorizedProjectReader {
  public function __construct(private PDO $db) {}

  /** @return list<array{id:int,code:string,title:string,role:string}> */
  public function forCompany(int $companyId, int $operatorId): array {
    if ($companyId < 1 || $operatorId < 1) return [];
    $stmt = $this->db->prepare(
      "SELECT p.id, p.code, p.title, cp.role_label
       FROM office_crm_company_projects cp
       JOIN office_projects p ON p.id = cp.project_id
       JOIN office_project_memberships m ON m.project_id = p.id
       JOIN office_operator_accounts a ON a.id = m.operator_id
       WHERE cp.company_id = :company_id AND m.operator_id = :operator_id
         AND m.permission IN ('read', 'manage') AND a.active = 1
       ORDER BY p.id DESC LIMIT 200"
    );
    $stmt->execute([':company_id' => $companyId, ':operator_id' => $operatorId]);
    return array_map(static fn(array $r): array => [
      'id' => (int)$r['id'],
      'code' => (string)$r['code'],
      'title' => (string)$r['title'],
      'role' => (string)$r['role_label'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
  }
}
