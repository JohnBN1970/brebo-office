<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use InvalidArgumentException;
use PDO;

/**
 * Privileged projection of verified Office master records for the mobile
 * bootstrap. This is a read model, not a second project master.
 * Import never grants access: active flags remain unchanged on updates
 * and newly projected records start inactive.
 */
final class OnSiteOfficeCatalogProjection {
  public function __construct(private PDO $db) {}

  public function employee(string $id, string $name, string $language = 'nl'): void {
    if (trim($id) === '' || trim($name) === '' || trim($language) === '') {
      throw new InvalidArgumentException('Incomplete verified Office employee');
    }
    $query = $this->db->prepare('INSERT INTO onsite_employees (employee_id,display_name,language,active)
      VALUES (?,?,?,0) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), language=VALUES(language)');
    $query->execute([$id,$name,$language]);
  }

  public function project(string $id, string $name): void {
    if (trim($id) === '' || strlen($id) > 32 || trim($name) === '') {
      throw new InvalidArgumentException('Incomplete verified Office project');
    }
    $query = $this->db->prepare('INSERT INTO onsite_projects (project_id,name,active)
      VALUES (?,?,0) ON DUPLICATE KEY UPDATE name=VALUES(name)');
    $query->execute([$id,$name]);
  }

  public function zone(string $id, string $projectId, string $name, float $latitude, float $longitude, float $radiusMetres): void {
    if (trim($id) === '' || strlen($id) > 32 || trim($projectId) === '' || strlen($projectId) > 32
      || trim($name) === '' || !is_finite($latitude) || !is_finite($longitude)
      || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180
      || !is_finite($radiusMetres) || $radiusMetres <= 0 || $radiusMetres > 10000) {
      throw new InvalidArgumentException('Invalid verified Office zone geometry');
    }
    $query = $this->db->prepare('INSERT INTO onsite_zones
      (zone_id,project_id,name,latitude,longitude,radius_metres,active)
      VALUES (?,?,?,?,?,?,0) ON DUPLICATE KEY UPDATE
      name=VALUES(name),latitude=VALUES(latitude),longitude=VALUES(longitude),radius_metres=VALUES(radius_metres)');
    $query->execute([$id,$projectId,$name,$latitude,$longitude,$radiusMetres]);
  }
}
