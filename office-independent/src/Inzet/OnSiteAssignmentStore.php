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
}
