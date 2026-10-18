<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use PDO;

/**
 * Privileged Office-side projection writer. The canonical Project/Inzet system
 * supplies verified identifiers and geometry; mobile endpoints never call this.
 * No schema creation, geocoding or implicit activation takes place here.
 */
final class OnSiteProjectionWriter {
  public function __construct(private PDO $db) {}

  public function assign(
    string $employeeId,
    string $projectId,
    string $zoneId,
    DateTimeImmutable $from,
    ?DateTimeImmutable $until = null
  ): void {
    if ($employeeId === '' || $projectId === '' || $zoneId === '' || ($until !== null && $until <= $from)) {
      throw new \InvalidArgumentException('Invalid verified Office assignment');
    }
    $query = $this->db->prepare('INSERT INTO onsite_project_zone_assignments
      (employee_id,project_id,zone_id,valid_from,valid_until,active)
      VALUES (?,?,?,?,?,0)');
    $query->execute([$employeeId,$projectId,$zoneId,$from->format('c'),$until?->format('c')]);
  }

  public function deactivate(string $employeeId, string $projectId, string $zoneId): int {
    if ($employeeId === '' || $projectId === '' || $zoneId === '') {
      throw new \InvalidArgumentException('Missing assignment identity');
    }
    $query = $this->db->prepare('UPDATE onsite_project_zone_assignments SET active = 0
      WHERE employee_id = ? AND project_id = ? AND zone_id = ?');
    $query->execute([$employeeId,$projectId,$zoneId]);
    return $query->rowCount();
  }
}
