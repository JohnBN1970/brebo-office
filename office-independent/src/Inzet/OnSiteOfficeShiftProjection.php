<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Privileged, explicitly scoped projection from native office_inzet_shifts.
 * Callers supply independently verified personnel and project-zone mappings.
 * Stages only; activation remains a separate authorization decision.
 */
final class OnSiteOfficeShiftProjection {
  public function __construct(private PDO $db, private OnSiteOfficePlanningBridge $bridge) {}

  /**
   * @param array<int,string> $verifiedEmployees person_id => OnSite employee_id
   * @param array<int,string> $verifiedZones project_id => OnSite zone_id
   */
  public function stageProject(int $projectId, array $verifiedEmployees, array $verifiedZones): int {
    if ($projectId < 1 || !isset($verifiedZones[$projectId]) || trim($verifiedZones[$projectId]) === '') {
      throw new \InvalidArgumentException('Missing verified Office project zone');
    }
    $query = $this->db->prepare('SELECT project_id, person_id, starts_at, ends_at, status
      FROM office_inzet_shifts WHERE project_id = ? AND status = ? ORDER BY starts_at, id');
    $query->execute([$projectId,'confirmed']);
    $staged = 0;
    $utc = new DateTimeZone('UTC');
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $shift) {
      $personId = (int)($shift['person_id'] ?? 0);
      if ($personId < 1 || !isset($verifiedEmployees[$personId]) || trim($verifiedEmployees[$personId]) === '') {
        continue;
      }
      $starts = (string)($shift['starts_at'] ?? '');
      $ends = (string)($shift['ends_at'] ?? '');
      if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$starts)
        || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$ends)) {
        continue;
      }
      $from = new DateTimeImmutable($starts,$utc);
      $until = new DateTimeImmutable($ends,$utc);
      if ($until <= $from) continue;
      if ($this->bridge->stageConfirmed($verifiedEmployees[$personId],(string)$projectId,
        $verifiedZones[$projectId],(string)$shift['status'],$from,$until)) {
        $staged++;
      }
    }
    return $staged;
  }
}
