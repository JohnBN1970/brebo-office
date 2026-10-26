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
    // A workbook can contain multiple projects. Never disclose even its filename
    // unless the operator is authorized for EVERY project linked to the import.
    // An import without project links is intentionally invisible.
    $sql = 'SELECT i.id, i.supplier_id, i.iso_year, i.iso_week,
                   i.source_sha256, i.source_filename, i.created_at
            FROM office_inzet_supplier_week_imports i
            WHERE EXISTS (
              SELECT 1 FROM office_inzet_supplier_week_import_projects ip
              WHERE ip.import_id = i.id
            )
            AND NOT EXISTS (
              SELECT 1 FROM office_inzet_supplier_week_import_projects ip
              WHERE ip.import_id = i.id AND (
                ip.project_id NOT IN ('.implode(',',$holders).')
                OR NOT EXISTS (
                  SELECT 1 FROM office_inzet_project_suppliers ps
                  WHERE ps.supplier_id = i.supplier_id AND ps.project_id = ip.project_id
                )
                OR NOT EXISTS (
                  SELECT 1 FROM office_project_memberships m
                  INNER JOIN office_operator_accounts a ON a.id = m.operator_id
                  WHERE m.project_id = ip.project_id AND m.operator_id = :operator
                    AND a.active = 1 AND m.permission IN (\'read\',\'manage\')
                )
              )
            )
            ORDER BY i.created_at DESC, i.id DESC LIMIT '.(int)$limit;
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
