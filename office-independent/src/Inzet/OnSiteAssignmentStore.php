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
    $date = (new \DateTimeImmutable($occurredAt))
      ->setTimezone(new \DateTimeZone('Europe/Amsterdam'))->format('Y-m-d');
    $q = $this->db->prepare('SELECT 1 FROM onsite_assignments a
      JOIN onsite_zones z ON z.project_id=a.project_id AND z.active=1
      WHERE a.employee_id=? AND a.project_id=? AND z.id=?
        AND a.starts_on<=? AND a.ends_on>=? LIMIT 1');
    $q->execute([$employeeId,$projectId,$zoneId,$date,$date]);
    return (bool) $q->fetchColumn();
  }

  /**
   * Refresh a single project projection from canonical Office Project/Inzet data.
   * The caller must authorize the sync; this store is not a second project master.
   */
  public function replaceProject(
    string $projectId,
    string $name,
    array $zones,
    array $assignments,
  ): void {
    if (!ctype_digit($projectId) || (int) $projectId < 1 || trim($name) === '') {
      throw new \InvalidArgumentException('Invalid project');
    }
    foreach ($zones as $zone) {
      if (!is_array($zone) || !ctype_digit((string) ($zone['id'] ?? ''))
        || (int) $zone['id'] < 1 || trim((string) ($zone['name'] ?? '')) === ''
        || !is_numeric($zone['latitude'] ?? null)
        || !is_numeric($zone['longitude'] ?? null)
        || !is_numeric($zone['radius_metres'] ?? null)
        || abs((float) $zone['latitude']) > 90
        || abs((float) $zone['longitude']) > 180
        || (float) $zone['radius_metres'] < 25
        || (float) $zone['radius_metres'] > 1000) {
        throw new \InvalidArgumentException('Invalid project zone');
      }
    }
    foreach ($assignments as $assignment) {
      if (!is_array($assignment)
        || trim((string) ($assignment['employee_id'] ?? '')) === ''
        || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', (string) ($assignment['starts_on'] ?? ''))
        || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', (string) ($assignment['ends_on'] ?? ''))
        || $assignment['starts_on'] > $assignment['ends_on']) {
        throw new \InvalidArgumentException('Invalid employee assignment');
      }
    }
    $this->db->beginTransaction();
    try {
      $upsert = $this->db->prepare('DELETE FROM onsite_projects WHERE id=?');
      $upsert->execute([$projectId]);
      $insert = $this->db->prepare('INSERT INTO onsite_projects(id,name) VALUES (?,?)');
      $insert->execute([$projectId,$name]);
      $this->db->prepare('DELETE FROM onsite_zones WHERE project_id=?')->execute([$projectId]);
      $this->db->prepare('DELETE FROM onsite_assignments WHERE project_id=?')->execute([$projectId]);
      $zoneInsert = $this->db->prepare('INSERT INTO onsite_zones(id,project_id,name,latitude,longitude,radius_metres,active) VALUES (?,?,?,?,?,?,?)');
      foreach ($zones as $zone) {
        $zoneInsert->execute([(string) $zone['id'],$projectId,$zone['name'],
          $zone['latitude'],$zone['longitude'],$zone['radius_metres'],
          !empty($zone['active']) ? 1 : 0]);
      }
      $assignmentInsert = $this->db->prepare('INSERT INTO onsite_assignments(employee_id,project_id,starts_on,ends_on) VALUES (?,?,?,?)');
      foreach ($assignments as $assignment) {
        $assignmentInsert->execute([$assignment['employee_id'],$projectId,
          $assignment['starts_on'],$assignment['ends_on']]);
      }
      $this->db->commit();
    } catch (\Throwable $error) {
      $this->db->rollBack();
      throw $error;
    }
  }

}
