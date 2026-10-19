<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Restricted personnel-number update through the existing Office tables.
 * Caller must supply a verified operator identity, never a browser-supplied one.
 */
final class OfficeInzetPersonnelProfileRepository {
  public function __construct(private PDO $db) {}

  public function updatePersonnelNumber(string $operatorId, int $projectId, int $personId, string $number): bool {
    if ($operatorId === '' || strlen($operatorId) > 128 || $projectId < 1 || $personId < 1) {
      throw new \InvalidArgumentException('Verified operator, project and employee required');
    }
    $number = trim($number);
    if ($number === '' || strlen($number) > 40 || preg_match('/^[a-zA-Z0-9._-]+$/D', $number) !== 1) {
      throw new \InvalidArgumentException('Invalid personnel number');
    }
    $collision = $this->db->prepare("SELECT id FROM office_inzet_people
      WHERE personnel_number = ? AND id <> ? LIMIT 1");
    $collision->execute([$number, $personId]);
    if ($collision->fetchColumn() !== false) {
      throw new \InvalidArgumentException('Personnel number already in use');
    }
    $statement = $this->db->prepare("UPDATE office_inzet_people p
      INNER JOIN office_inzet_project_team t ON t.person_id = p.id AND t.project_id = ?
      INNER JOIN office_project_memberships m ON m.project_id = t.project_id
      INNER JOIN office_operator_accounts a ON a.id = m.operator_id
      SET p.personnel_number = ?
      WHERE p.id = ? AND p.active = 1 AND m.operator_id = ?
        AND m.permission = 'manage' AND a.active = 1");
    $statement->execute([$projectId, $number, $personId, $operatorId]);
    return $statement->rowCount() > 0;
  }
}
