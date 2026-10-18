<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
use PDO;

/** Independent Office projection for OnSite assignments and project zones. */
final class OnSiteAssignmentStore {
  public function __construct(private PDO $db) {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  public function migrate(): void {
    $this->db->exec('CREATE TABLE IF NOT EXISTS onsite_projects (
      id TEXT PRIMARY KEY, name TEXT NOT NULL
    )');
    $this->db->exec('CREATE TABLE IF NOT EXISTS onsite_zones (
      id TEXT PRIMARY KEY, project_id TEXT NOT NULL, name TEXT NOT NULL,
      latitude REAL NOT NULL, longitude REAL NOT NULL,
      radius_metres REAL NOT NULL, active INTEGER NOT NULL DEFAULT 1
    )');
    $this->db->exec('CREATE TABLE IF NOT EXISTS onsite_assignments (
      employee_id TEXT NOT NULL, project_id TEXT NOT NULL,
      starts_on TEXT NOT NULL, ends_on TEXT NOT NULL,
      PRIMARY KEY (employee_id, project_id, starts_on)
    )');
  }
  public function projectsForEmployee(string $employeeId, string $date): array {
    $q = $this->db->prepare('SELECT p.id,p.name FROM onsite_projects p
      JOIN onsite_assignments a ON a.project_id=p.id
      WHERE a.employee_id=? AND a.starts_on<=? AND a.ends_on>=?
      ORDER BY p.name,p.id');
    $q->execute([$employeeId,$date,$date]);
    $zones = $this->db->prepare('SELECT id,name,latitude,longitude,radius_metres
      FROM onsite_zones WHERE project_id=? AND active=1 ORDER BY id');
    $projects = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $project) {
      $zones->execute([$project['id']]);
      $project['zones'] = array_map(static fn(array $zone): array => [
        'id' => $zone['id'], 'name' => $zone['name'],
        'latitude' => (float) $zone['latitude'],
        'longitude' => (float) $zone['longitude'],
        'radius_metres' => (float) $zone['radius_metres'],
      ], $zones->fetchAll(PDO::FETCH_ASSOC));
      $projects[] = $project;
    }
    return $projects;
  }

  public function isAssigned(string $employeeId, string $projectId, string $zoneId, string $occurredAt): bool {
    $date = (new \DateTimeImmutable($occurredAt))->format('Y-m-d');
    $q = $this->db->prepare('SELECT 1 FROM onsite_assignments a
      JOIN onsite_zones z ON z.project_id=a.project_id AND z.active=1
      WHERE a.employee_id=? AND a.project_id=? AND z.id=?
        AND a.starts_on<=? AND a.ends_on>=? LIMIT 1');
    $q->execute([$employeeId,$projectId,$zoneId,$date,$date]);
    return (bool) $q->fetchColumn();
  }

}
