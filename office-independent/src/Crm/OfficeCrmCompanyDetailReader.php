<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

use PDO;

/**
 * Read-only company detail. Project and building references are identifiers,
 * not links: controllers must apply the relevant record-level ACL before
 * displaying protected project or building information.
 */
final class OfficeCrmCompanyDetailReader {
  public function __construct(private PDO $db) {}

  public function company(int $id): ?array {
    if ($id < 1) return null;
    $stmt = $this->db->prepare(
      'SELECT id, name, email, phone FROM office_crm_companies WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
  }

  public function contacts(int $companyId): array {
    if ($companyId < 1) return [];
    $stmt = $this->db->prepare(
      'SELECT p.first_name, p.last_name, p.email, cc.role_label
       FROM office_crm_company_contacts cc
       JOIN office_crm_contacts p ON p.id = cc.contact_id
       WHERE cc.company_id = :id
       ORDER BY p.last_name, p.first_name LIMIT 200'
    );
    $stmt->execute([':id' => $companyId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function buildingReferences(int $companyId): array {
    return $this->references('office_crm_company_buildings', 'building_id', $companyId);
  }

  public function projectReferences(int $companyId): array {
    return $this->references('office_crm_company_projects', 'project_id', $companyId);
  }

  private function references(string $table, string $column, int $companyId): array {
    if ($companyId < 1) return [];
    // Identifiers are fixed internal literals, never user input.
    $stmt = $this->db->prepare(
      "SELECT {$column} AS record_id, role_label FROM {$table}
       WHERE company_id = :id ORDER BY {$column} LIMIT 200"
    );
    $stmt->execute([':id' => $companyId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
