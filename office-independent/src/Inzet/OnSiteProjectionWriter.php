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
    // Idempotent staging: repeated canonical planning exports must not
    // duplicate assignments or silently reactivate a revoked assignment.
    $query = $this->db->prepare('SELECT valid_until FROM onsite_project_zone_assignments
      WHERE employee_id = ? AND project_id = ? AND zone_id = ? AND valid_from = ?');
    $identity = [$employeeId,$projectId,$zoneId,$from->format('c')];
    $query->execute($identity);
    $existing = $query->fetchColumn();
    $end = $until?->format('c');
    if ($existing !== false) {
      if ($existing !== $end) {
        throw new \DomainException('Existing assignment window differs; explicit revision required');
      }
      return;
    }
    $query = $this->db->prepare('INSERT INTO onsite_project_zone_assignments
      (employee_id,project_id,zone_id,valid_from,valid_until,active)
      VALUES (?,?,?,?,?,0)');
    $query->execute([...$identity,$end]);
  }

  /**
   * Activate only a previously staged assignment after independent Office
   * verification of employee, project, zone and planning authorization.
   */
  public function activate(string $employeeId, string $projectId, string $zoneId, DateTimeImmutable $from): bool {
    if ($employeeId === '' || $projectId === '' || $zoneId === '') {
      throw new \InvalidArgumentException('Missing verified assignment identity');
    }
    $query = $this->db->prepare('UPDATE onsite_project_zone_assignments SET active = 1
      WHERE employee_id = ? AND project_id = ? AND zone_id = ? AND valid_from = ?
      AND EXISTS (SELECT 1 FROM onsite_employees e WHERE e.employee_id = ? AND e.active = 1)
      AND EXISTS (SELECT 1 FROM onsite_projects p WHERE p.project_id = ? AND p.active = 1)
      AND EXISTS (SELECT 1 FROM onsite_zones z
        WHERE z.project_id = ? AND z.zone_id = ? AND z.active = 1)');
    $query->execute([$employeeId,$projectId,$zoneId,$from->format('c'),$employeeId,$projectId,$projectId,$zoneId]);
    return $query->rowCount() > 0;
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
