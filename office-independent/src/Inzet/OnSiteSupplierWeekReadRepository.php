<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Read-only supplier-week register. Caller passes project IDs derived from
 * server-side operator project access; never accept them from untrusted input.
 * Supplier visibility requires an explicit project-supplier assignment.
 */
final class OnSiteSupplierWeekReadRepository {
  public function __construct(private PDO $db) {}

  /**
   * @param list<int> $authorizedProjectIds Verified Office project IDs for operator
   * @return list<array<string,mixed>>
   */
  public function listVisible(int $operatorId, array $authorizedProjectIds, int $limit = 100): array {
    if ($operatorId < 1 || $limit < 1 || $limit > 200) {
      throw new \InvalidArgumentException('Invalid operator or page size.');
    }
    $projects = array_values(array_unique(array_filter(
      $authorizedProjectIds, static fn(mixed $id): bool => is_int($id) && $id > 0
    )));
    if ($projects === []) return [];
    $holders = [];
    $params = [':operator'=>$operatorId];
    foreach ($projects as $index=>$id) {
      $key = ':project'.$index;
      $holders[] = $key;
      $params[$key] = $id;
    }
    // Explicit supplier assignment AND active operator project membership.
    // DISTINCT avoids duplicate imports when a supplier works on multiple projects.
    $sql = 'SELECT DISTINCT i.id, i.supplier_id, i.iso_year, i.iso_week,
                   i.source_sha256, i.source_filename, i.created_at
            FROM office_inzet_supplier_week_imports i
            INNER JOIN office_inzet_supplier_week_import_projects ip ON ip.import_id = i.id
            INNER JOIN office_inzet_project_suppliers ps ON ps.supplier_id = i.supplier_id AND ps.project_id = ip.project_id
            INNER JOIN office_project_memberships m ON m.project_id = ps.project_id
            INNER JOIN office_operator_accounts a ON a.id = m.operator_id
            WHERE m.operator_id = :operator AND a.active = 1
              AND m.permission IN (\'read\',\'manage\')
              AND ps.project_id IN ('.implode(',',$holders).')
            ORDER BY i.created_at DESC, i.id DESC LIMIT '.(int)$limit;
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
