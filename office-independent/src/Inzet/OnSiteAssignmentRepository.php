<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use PDO;

/**
 * Office-owned OnSite assignment read model. Provisioning is a separate,
 * authenticated Office management action; mobile requests cannot write here.
 */
final class OnSiteAssignmentRepository {
  public function __construct(private PDO $db) {}

  public function migrate(): void {
    $this->db->exec('CREATE TABLE IF NOT EXISTS onsite_project_zone_assignments (
      employee_id TEXT NOT NULL, project_id TEXT NOT NULL, zone_id TEXT NOT NULL,
      valid_from TEXT NOT NULL, valid_until TEXT NULL, active INTEGER NOT NULL DEFAULT 0,
      PRIMARY KEY (employee_id, project_id, zone_id, valid_from)
    )');
  }

  public function allows(string $employeeId, string $projectId, string $zoneId, string $occurredAt): bool {
    if ($employeeId === '' || $projectId === '' || $zoneId === '') return false;
    try { $time = new DateTimeImmutable($occurredAt); }
    catch (\Throwable) { return false; }
    $q = $this->db->prepare('SELECT valid_from, valid_until FROM onsite_project_zone_assignments
      WHERE employee_id = ? AND project_id = ? AND zone_id = ? AND active = 1');
    $q->execute([$employeeId, $projectId, $zoneId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
      try {
        $start = new DateTimeImmutable($row['valid_from']);
        $end = $row['valid_until'] === null ? null : new DateTimeImmutable($row['valid_until']);
      } catch (\Throwable) { continue; }
      if ($time >= $start && ($end === null || $time < $end)) return true;
    }
    return false;
  }
}
