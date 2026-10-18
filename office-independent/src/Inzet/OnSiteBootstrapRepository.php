<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use PDO;
use RuntimeException;

/**
 * Reads only Office-owned, explicitly active employee and zone records.
 * No mobile-provided identity, fallback geocoding or legacy Drupal dependency.
 */
final class OnSiteBootstrapRepository {
  public function __construct(private PDO $db) {}

  /** @return array{employee:array<string,mixed>,projects:array<int,array<string,mixed>>} */
  public function forEmployee(string $employeeId, ?DateTimeImmutable $now = null): array {
    if ($employeeId === '') throw new RuntimeException('Missing verified employee identity');
    $employeeQuery = $this->db->prepare('SELECT employee_id, display_name, language FROM onsite_employees WHERE employee_id = ? AND active = 1');
    $employeeQuery->execute([$employeeId]);
    $employee = $employeeQuery->fetch(PDO::FETCH_ASSOC);
    if (!is_array($employee)) throw new RuntimeException('Employee not active');
    $query = $this->db->prepare('SELECT a.project_id, a.zone_id, p.name AS project_name,
      z.name AS zone_name, z.latitude, z.longitude, z.radius_metres,
      a.valid_from, a.valid_until
      FROM onsite_project_zone_assignments a
      INNER JOIN onsite_projects p ON p.project_id = a.project_id AND p.active = 1
      INNER JOIN onsite_zones z ON z.zone_id = a.zone_id AND z.project_id = a.project_id AND z.active = 1
      WHERE a.employee_id = ? AND a.active = 1
      ORDER BY a.project_id, a.zone_id');
    $query->execute([$employeeId]);
    $projects = [];
    $at = $now ?? new DateTimeImmutable('now');
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
      try {
        $start = new DateTimeImmutable($row['valid_from']);
        $end = $row['valid_until'] === null ? null : new DateTimeImmutable($row['valid_until']);
      } catch (\Throwable) { continue; }
      if ($at < $start || ($end !== null && $at >= $end)) continue;
      $id = (string)$row['project_id'];
      if (!isset($projects[$id])) {
        $projects[$id] = ['id'=>$id,'name'=>$row['project_name'],'zones'=>[]];
      }
      $projects[$id]['zones'][(string)$row['zone_id']] = [
        'id'=>(string)$row['zone_id'],'name'=>$row['zone_name'],
        'latitude'=>(float)$row['latitude'],'longitude'=>(float)$row['longitude'],
        'radius_metres'=>(float)$row['radius_metres'],'active'=>true,
      ];
    }
    return ['employee'=>[
      'id'=>$employeeId,'display_name'=>$employee['display_name'],'language'=>$employee['language'],
    ],'projects'=>array_values(array_map(static function(array $project): array {
      $project['zones'] = array_values($project['zones']);
      return $project;
    }, $projects))];
  }
}
