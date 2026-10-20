<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use InvalidArgumentException;

/** Office-owned device inventory; never queries the legacy onsite_employees tables. */
final class OnSiteOfficeDeviceManagementRepository {
  public function __construct(private PDO $db) {}

  /** @return list<array<string,mixed>> */
  public function listForManager(string $operatorId, int $projectId): array {
    if ($operatorId === '' || strlen($operatorId) > 128 || $projectId <= 0) {
      throw new InvalidArgumentException('Verified Office manager and project required');
    }
    $query = $this->db->prepare("SELECT p.id, p.display_name,
      COUNT(DISTINCT d.token_hash) AS active_device_count
      FROM office_inzet_people p
      INNER JOIN office_inzet_project_team t ON t.person_id = p.id
      INNER JOIN office_project_memberships m ON m.project_id = t.project_id
      INNER JOIN office_operator_accounts a ON a.id = m.operator_id
      LEFT JOIN office_onsite_device_tokens d ON d.person_id = p.id
        AND d.revoked_at IS NULL
        AND (d.expires_at IS NULL OR d.expires_at > UTC_TIMESTAMP())
      WHERE t.project_id = ? AND m.project_id = t.project_id
        AND m.operator_id = ? AND m.permission = 'manage'
        AND a.active = 1 AND p.active = 1
      GROUP BY p.id, p.display_name
      ORDER BY p.display_name, p.id LIMIT 500");
    $query->execute([$projectId, $operatorId]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return list<array<string,mixed>> */
  public function listDeviceCredentialsForManager(string $operatorId, int $projectId): array {
    if ($operatorId === '' || strlen($operatorId) > 128 || $projectId <= 0) {
      throw new InvalidArgumentException('Verified Office manager and project required');
    }
    $query = $this->db->prepare("SELECT DISTINCT p.id AS person_id, p.display_name, d.token_hash
      FROM office_inzet_people p
      INNER JOIN office_inzet_project_team t ON t.person_id = p.id
      INNER JOIN office_project_memberships m ON m.project_id = t.project_id
      INNER JOIN office_operator_accounts a ON a.id = m.operator_id
      INNER JOIN office_onsite_device_tokens d ON d.person_id = p.id
      WHERE t.project_id = ? AND m.project_id = t.project_id
        AND m.operator_id = ? AND m.permission = 'manage'
        AND a.active = 1 AND p.active = 1 AND d.revoked_at IS NULL
        AND (d.expires_at IS NULL OR d.expires_at > UTC_TIMESTAMP())
      ORDER BY p.display_name, p.id, d.token_hash LIMIT 500");
    $query->execute([$projectId, $operatorId]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
  }
}
