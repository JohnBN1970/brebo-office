<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeZone;
use InvalidArgumentException;

/**
 * Privileged Office import boundary for a single canonical Project/Inzet row.
 * A trusted caller must resolve the legacy user id to a verified OnSite
 * employee and the canonical project to an existing verified zone.
 * This is not a mobile HTTP endpoint.
 */
final class OnSitePlanningImport {
  public function __construct(private OnSitePlanningSynchronizer $synchronizer) {}

  /**
   * @param array<string,mixed> $row Canonical PersonnelAssignmentRepository record.
   * @param callable(int):?string $employeeForUser Trusted Office identity resolver.
   * @param callable(int):?string $zoneForProject Trusted Office zone resolver.
   */
  public function import(array $row, callable $employeeForUser, callable $zoneForProject, ?DateTimeZone $timezone = null): bool {
    $userId = (int)($row['user_id'] ?? 0);
    $projectId = (int)($row['project_id'] ?? 0);
    if ($userId <= 0 || $projectId <= 0) throw new InvalidArgumentException('Invalid canonical assignment identifiers');
    if (($row['assignment_status'] ?? '') !== 'confirmed') return false;
    $employee = $employeeForUser($userId);
    $zone = $zoneForProject($projectId);
    if (!is_string($employee) || trim($employee) === ''
      || !is_string($zone) || trim($zone) === '') {
      throw new InvalidArgumentException('Verified Office employee or project zone mapping missing');
    }
    return $this->synchronizer->stage($row,$employee,$zone,$timezone);
  }
}
